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
     * Get analytics summary (Total Revenue, Orders, AOV).
     */
    public function summary(Request $request)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }
        $startDate = $request->query('start_date', Carbon::now()->subDays(30)->toDateString());
        $endDate = $request->query('end_date', Carbon::now()->toDateString());

        $query = Order::whereBetween('created_at', [
            Carbon::parse($startDate)->startOfDay(),
            Carbon::parse($endDate)->endOfDay()
        ])->whereNotIn('status', ['cancelled', 'pending']);

        $totalRevenue = $query->sum('final_amount');
        $totalOrders = $query->count();
        $averageOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

        // Calculate average completion time
        $driver = DB::connection()->getDriverName();
        $completedQuery = clone $query;
        $completedQuery->where('status', 'completed')->whereNotNull('completed_at');

        if ($driver === 'sqlite') {
            $avgCompletionTime = $completedQuery->select(DB::raw("AVG((julianday(completed_at) - julianday(created_at)) * 24 * 60) as avg_completion_minutes"))->value('avg_completion_minutes');
        } else {
            // MySQL/PostgreSQL
            $avgCompletionTime = $completedQuery->select(DB::raw("AVG(TIMESTAMPDIFF(MINUTE, created_at, completed_at)) as avg_completion_minutes"))->value('avg_completion_minutes');
        }

        return response()->json([
            'total_revenue' => round($totalRevenue, 2),
            'total_orders' => $totalOrders,
            'average_order_value' => round($averageOrderValue, 2),
            'average_completion_minutes' => round($avgCompletionTime ?? 0, 2),
            'period' => [
                'start' => $startDate,
                'end' => $endDate
            ]
        ]);
    }

    /**
     * Get top selling menus.
     */
    public function topMenus(Request $request)
    {
        $limit = $request->query('limit', 5);
        $startDate = $request->query('start_date', Carbon::now()->subDays(30)->toDateString());
        $endDate = $request->query('end_date', Carbon::now()->toDateString());

        $topMenus = OrderItem::select('menu_id', DB::raw('SUM(quantity) as total_sold'), DB::raw('SUM(subtotal) as total_revenue'))
            ->whereHas('order', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('created_at', [
                    Carbon::parse($startDate)->startOfDay(),
                    Carbon::parse($endDate)->endOfDay()
                ])->whereNotIn('status', ['cancelled', 'pending']);
            })
            ->with('menu:id,name,price')
            ->groupBy('menu_id')
            ->orderByDesc('total_sold')
            ->limit($limit)
            ->get();

        return response()->json($topMenus);
    }

    /**
     * Get daily sales data for charts.
     */
    public function dailySales(Request $request)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        // Accept either month+year OR explicit start/end dates
        if ($request->filled('month') && $request->filled('year')) {
            $start = Carbon::createFromDate($request->year, $request->month, 1)->startOfMonth();
            $end   = $start->copy()->endOfMonth();
        } else {
            $start = Carbon::parse($request->query('start_date', Carbon::now()->startOfMonth()->toDateString()))->startOfDay();
            $end   = Carbon::parse($request->query('end_date',   Carbon::now()->toDateString()))->endOfDay();
        }

        $driver = DB::connection()->getDriverName();
        $groupByExpression = $driver === 'sqlite'
            ? "strftime('%Y-%m-%d', created_at)"
            : "DATE(created_at)";

        $avgCompletionExpression = $driver === 'sqlite'
            ? "AVG((julianday(completed_at) - julianday(created_at)) * 24 * 60)"
            : "AVG(TIMESTAMPDIFF(MINUTE, created_at, completed_at))";

        $rows = Order::select(
                DB::raw("$groupByExpression as date"),
                DB::raw('SUM(final_amount) as revenue'),
                DB::raw('COUNT(*) as orders_count'),
                DB::raw("ROUND($avgCompletionExpression, 2) as avg_completion_minutes")
            )
            ->whereBetween('created_at', [$start, $end])
            ->whereNotIn('status', ['cancelled', 'pending'])
            ->groupBy(DB::raw($groupByExpression))
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        // Fill in missing days with zero values so charts have no gaps
        $period = Carbon::parse($start)->toPeriod($end, '1 day');
        $filled = [];

        foreach ($period as $day) {
            $key = $day->toDateString();
            $filled[] = [
                'date'                   => $key,
                'revenue'                => isset($rows[$key]) ? (float) $rows[$key]->revenue : 0,
                'orders_count'           => isset($rows[$key]) ? (int) $rows[$key]->orders_count : 0,
                'avg_completion_minutes' => isset($rows[$key]) ? (float) $rows[$key]->avg_completion_minutes : 0,
            ];
        }

        return response()->json([
            'period' => [
                'start' => $start->toDateString(),
                'end'   => $end->toDateString(),
            ],
            'data' => $filled,
        ]);
    }
}
