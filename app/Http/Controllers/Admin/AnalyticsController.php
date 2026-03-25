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
        ])->where('status', '!=', 'cancelled'); // Assuming cancelled orders don't count? Or maybe 'completed'?

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
                ])->where('status', '!=', 'cancelled');
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
        $startDate = $request->query('start_date', Carbon::now()->subDays(7)->toDateString());
        $endDate = $request->query('end_date', Carbon::now()->toDateString());

        // We can use DB raw date formatting for grouping by day.
        // Compatible with SQLite/MySQL usually via strftime or DATE().
        // For broad compatibility let's use a simpler approach or raw expression carefully.

        // SQLite uses strftime('%Y-%m-%d', created_at)
        // MySQL uses DATE(created_at) or DATE_FORMAT(created_at, '%Y-%m-%d')

        // Let's assume standard Laravel setup usually uses MySQL in production but SQLite in testing.
        // Let's use DB::raw depending on driver or just fetch and map in PHP if dataset is small?
        // But for analytics, DB grouping is better.

        $driver = DB::connection()->getDriverName();
        $groupByExpression = $driver === 'sqlite'
            ? "strftime('%Y-%m-%d', created_at)"
            : "DATE(created_at)";

        $avgCompletionExpression = $driver === 'sqlite'
            ? "AVG((julianday(completed_at) - julianday(created_at)) * 24 * 60)"
            : "AVG(TIMESTAMPDIFF(MINUTE, created_at, completed_at))";

        $dailySales = Order::select(
                DB::raw("$groupByExpression as date"),
                DB::raw('SUM(final_amount) as revenue'),
                DB::raw('COUNT(*) as orders_count'),
                DB::raw("ROUND($avgCompletionExpression, 2) as avg_completion_minutes")
            )
            ->whereBetween('created_at', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay()
            ])
            ->where('status', '!=', 'cancelled')
            ->groupBy(DB::raw('date')) // Use the alias 'date' for grouping if supported, otherwise repeat expression
            ->orderBy('date')
            ->get();

        // If 'date' alias doesn't work in group by (standard SQL requires expression), we might need:
        // ->groupBy(DB::raw($groupByExpression))

        return response()->json($dailySales);
    }
}
