<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AnalyticsController extends Controller
{
    /**
     * Resolve start/end Carbon instances from preset or explicit dates.
     * Priority: preset > month+year > start_date/end_date > default (last 30 days)
     */
    private function resolveDateRange(Request $request): array
    {
        $preset = $request->query('preset');

        switch ($preset) {
            case 'today':
                return [Carbon::today()->startOfDay(), Carbon::today()->endOfDay()];
            case 'this_week':
                return [Carbon::now()->startOfWeek()->startOfDay(), Carbon::now()->endOfDay()];
            case 'this_month':
                return [Carbon::now()->startOfMonth()->startOfDay(), Carbon::now()->endOfDay()];
            case 'last_month':
                return [
                    Carbon::now()->subMonthNoOverflow()->startOfMonth()->startOfDay(),
                    Carbon::now()->subMonthNoOverflow()->endOfMonth()->endOfDay(),
                ];
            case 'this_year':
                return [Carbon::now()->startOfYear()->startOfDay(), Carbon::now()->endOfDay()];
            case 'all_time':
                $earliest = Order::min('created_at');
                $start = $earliest ? Carbon::parse($earliest)->startOfDay() : Carbon::now()->startOfDay();
                return [$start, Carbon::now()->endOfDay()];
            case 'custom':
                return [
                    Carbon::parse($request->query('start_date', Carbon::now()->startOfMonth()->toDateString()))->startOfDay(),
                    Carbon::parse($request->query('end_date', Carbon::now()->toDateString()))->endOfDay(),
                ];
        }

        // Legacy: month+year params
        if ($request->filled('month') && $request->filled('year')) {
            $start = Carbon::createFromDate($request->year, $request->month, 1)->startOfMonth();
            return [$start, $start->copy()->endOfMonth()->endOfDay()];
        }

        // Legacy: explicit start_date/end_date or default last 30 days
        return [
            Carbon::parse($request->query('start_date', Carbon::now()->subDays(30)->toDateString()))->startOfDay(),
            Carbon::parse($request->query('end_date', Carbon::now()->toDateString()))->endOfDay(),
        ];
    }

    /**
     * Determine chart granularity based on date range span.
     */
    private function resolveGranularity(Carbon $start, Carbon $end): string
    {
        $days = $start->diffInDays($end);
        if ($days <= 60) return 'day';
        if ($days <= 365) return 'week';
        return 'month';
    }

    /**
     * Get analytics summary (Total Revenue, Orders, AOV).
     */
    public function summary(Request $request)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        [$start, $end] = $this->resolveDateRange($request);

        $query = Order::whereBetween('created_at', [$start, $end])
            ->whereNotIn('status', ['cancelled', 'pending']);

        $totalRevenue = $query->sum('final_amount');
        $totalOrders  = $query->count();
        $averageOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

        $driver = DB::connection()->getDriverName();
        $completedQuery = clone $query;
        $completedQuery->where('status', 'completed')->whereNotNull('completed_at');

        $avgCompletionTime = $driver === 'sqlite'
            ? $completedQuery->select(DB::raw("AVG((julianday(completed_at) - julianday(created_at)) * 24 * 60) as avg_completion_minutes"))->value('avg_completion_minutes')
            : $completedQuery->select(DB::raw("AVG(TIMESTAMPDIFF(MINUTE, created_at, completed_at)) as avg_completion_minutes"))->value('avg_completion_minutes');

        return response()->json([
            'total_revenue'              => round($totalRevenue, 2),
            'total_orders'               => $totalOrders,
            'average_order_value'        => round($averageOrderValue, 2),
            'average_completion_minutes' => round($avgCompletionTime ?? 0, 2),
            'period' => [
                'start' => $start->toDateString(),
                'end'   => $end->toDateString(),
            ],
        ]);
    }

    /**
     * Get top selling menus.
     */
    public function topMenus(Request $request)
    {
        $limit = $request->query('limit', 5);
        [$start, $end] = $this->resolveDateRange($request);

        $topMenus = OrderItem::select('menu_id', DB::raw('SUM(quantity) as total_sold'), DB::raw('SUM(subtotal) as total_revenue'))
            ->whereHas('order', function ($q) use ($start, $end) {
                $q->whereBetween('created_at', [$start, $end])
                  ->whereNotIn('status', ['cancelled', 'pending']);
            })
            ->with('menu:id,name,price')
            ->groupBy('menu_id')
            ->orderByDesc('total_sold')
            ->limit($limit)
            ->get();

        return response()->json($topMenus);
    }

    /**
     * Get sales data for charts, with auto granularity (day/week/month).
     */
    public function dailySales(Request $request)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        [$start, $end] = $this->resolveDateRange($request);
        $granularity   = $this->resolveGranularity($start, $end);
        $driver        = DB::connection()->getDriverName();

        [$groupExpr, $labelExpr, $interval] = $this->granularityExpressions($granularity, $driver);

        $avgCompletionExpr = $driver === 'sqlite'
            ? "AVG((julianday(completed_at) - julianday(created_at)) * 24 * 60)"
            : "AVG(TIMESTAMPDIFF(MINUTE, created_at, completed_at))";

        $rows = Order::select(
                DB::raw("$labelExpr as date"),
                DB::raw('SUM(final_amount) as revenue'),
                DB::raw('COUNT(*) as orders_count'),
                DB::raw("ROUND($avgCompletionExpr, 2) as avg_completion_minutes")
            )
            ->whereBetween('created_at', [$start, $end])
            ->whereNotIn('status', ['cancelled', 'pending'])
            ->groupBy(DB::raw($groupExpr))
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $filled = [];
        foreach ($start->copy()->toPeriod($end, $interval) as $point) {
            $key = $this->periodKey($point, $granularity);
            $filled[] = [
                'date'                   => $key,
                'revenue'                => isset($rows[$key]) ? (float) $rows[$key]->revenue : 0,
                'orders_count'           => isset($rows[$key]) ? (int)   $rows[$key]->orders_count : 0,
                'avg_completion_minutes' => isset($rows[$key]) ? (float) $rows[$key]->avg_completion_minutes : 0,
            ];
        }

        return response()->json([
            'period' => [
                'start' => $start->toDateString(),
                'end'   => $end->toDateString(),
            ],
            'granularity' => $granularity,
            'data'        => $filled,
        ]);
    }

    /**
     * Return [groupByExpr, labelExpr, carbonInterval] for the given granularity + driver.
     */
    private function granularityExpressions(string $granularity, string $driver): array
    {
        if ($granularity === 'week') {
            if ($driver === 'sqlite') {
                $expr = "strftime('%Y-W%W', created_at)";
            } else {
                $expr = "DATE_FORMAT(created_at, '%x-W%v')"; // ISO year-week
            }
            return [$expr, $expr, '1 week'];
        }

        if ($granularity === 'month') {
            if ($driver === 'sqlite') {
                $expr = "strftime('%Y-%m', created_at)";
            } else {
                $expr = "DATE_FORMAT(created_at, '%Y-%m')";
            }
            return [$expr, $expr, '1 month'];
        }

        // day (default)
        $expr = $driver === 'sqlite' ? "strftime('%Y-%m-%d', created_at)" : "DATE(created_at)";
        return [$expr, $expr, '1 day'];
    }

    /**
     * Format a Carbon period point into the key used in the DB result.
     */
    private function periodKey(Carbon $point, string $granularity): string
    {
        return match ($granularity) {
            'week'  => $point->format('o-\WW'), // ISO year-week e.g. 2025-W03
            'month' => $point->format('Y-m'),
            default => $point->toDateString(),
        };
    }
}
