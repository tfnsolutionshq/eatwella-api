<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use App\Models\MenuRecommendation;
use App\Models\RecommendationLog;
use App\Models\Cart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RecommendationController extends Controller
{
    /**
     * Get real-time recommendations based on cart items.
     * Response time targeted < 200ms.
     */
    public function getCartRecommendations(Request $request)
    {
        $startTime = microtime(true);
        
        $request->validate([
            'menu_ids' => 'nullable|array',
            'menu_ids.*' => 'uuid|exists:menus,id',
            'limit' => 'integer|min:1|max:10'
        ]);

        $menuIds = $request->menu_ids;
        if (empty($menuIds)) {
            $user = auth('sanctum')->user();
            if ($user) {
                $cart = Cart::where('user_id', $user->id)->with('items')->first();
            } else {
                $cartId = $request->header('X-Cart-ID');
                $cart = $cartId ? Cart::where('session_id', $cartId)->with('items')->first() : null;
            }
            $menuIds = $cart ? $cart->items->pluck('menu_id')->unique()->values()->all() : [];
        }
        if (empty($menuIds)) {
            return response()->json([
                'data' => [],
                'ab_test_group' => null,
                'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2)
            ]);
        }
        $limit = $request->input('limit', 4);
        
        // Determine A/B Test Group (pseudo-random based on session or timestamp)
        // Group A: Collaborative only
        // Group B: Hybrid (Collaborative + Content)
        $abTestGroup = (crc32(session()->getId() ?? (string) microtime()) % 2 === 0) ? 'A' : 'B';

        // Cache key based on sorted menu IDs and AB group to ensure fast retrieval
        sort($menuIds);
        $cacheKey = 'recs_cart_' . md5(implode('_', $menuIds)) . '_group_' . $abTestGroup . '_limit_' . $limit;

        $recommendations = Cache::remember($cacheKey, now()->addMinutes(60), function () use ($menuIds, $abTestGroup, $limit) {
            $query = MenuRecommendation::select('recommended_menu_id', DB::raw('SUM(score) as total_score'))
                ->whereIn('menu_id', $menuIds)
                ->whereNotIn('recommended_menu_id', $menuIds) // Exclude items already in cart
                ->groupBy('recommended_menu_id');

            if ($abTestGroup === 'A') {
                $query->where('algorithm', 'collaborative');
            }

             $recommendedIdsAndScores = $query->orderByDesc('total_score')
                ->limit($limit)
                ->get()
                ->keyBy('recommended_menu_id');

            if ($recommendedIdsAndScores->isEmpty()) {
                 $fallbackQuery = MenuRecommendation::select('recommended_menu_id', DB::raw('SUM(score) as total_score'))
                     ->whereIn('menu_id', $menuIds)
                     ->whereNotIn('recommended_menu_id', $menuIds)
                     ->groupBy('recommended_menu_id')
                     ->orderByDesc('total_score')
                     ->limit($limit);
                 $recommendedIdsAndScores = $fallbackQuery->get()->keyBy('recommended_menu_id');
                 if ($recommendedIdsAndScores->isEmpty()) {
                     return [];
                 }
            }

            $menus = Menu::whereIn('id', $recommendedIdsAndScores->keys())
                ->where('is_available', true)
                ->get();

            // Map scores back to the models
            return $menus->map(function ($menu) use ($recommendedIdsAndScores) {
                $menu->recommendation_score = round($recommendedIdsAndScores[$menu->id]->total_score, 2);
                return $menu;
            })->sortByDesc('recommendation_score')->values();
        });

        $responseTimeMs = round((microtime(true) - $startTime) * 1000, 2);

        return response()->json([
            'data' => $recommendations,
            'ab_test_group' => $abTestGroup,
            'response_time_ms' => $responseTimeMs
        ]);
    }

    /**
     * Track user interaction with recommendations for A/B testing analysis.
     */
    public function trackInteraction(Request $request)
    {
        $validated = $request->validate([
            'recommended_menu_id' => 'required|uuid|exists:menus,id',
            'ab_test_group' => 'required|string|in:A,B',
            'action' => 'required|string|in:view,click,add_to_cart,purchase'
        ]);

        RecommendationLog::create([
            'user_id' => auth('sanctum')->id(),
            'session_id' => session()->getId() ?? $request->ip(),
            'recommended_menu_id' => $validated['recommended_menu_id'],
            'ab_test_group' => $validated['ab_test_group'],
            'action' => $validated['action'],
        ]);

        return response()->json(['message' => 'Interaction tracked successfully.']);
    }
}
