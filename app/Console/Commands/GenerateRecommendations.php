<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use App\Models\Menu;
use App\Models\MenuRecommendation;
use Illuminate\Support\Facades\DB;

class GenerateRecommendations extends Command
{
    protected $signature = 'recommendations:generate';
    protected $description = 'Generate and pre-compute menu recommendations based on collaborative and content-based filtering.';

    public function handle()
    {
        $this->info('Starting recommendation generation...');

        // Clear old recommendations
        MenuRecommendation::truncate();

        // 1. Collaborative Filtering (Frequently Bought Together)
        $this->info('Processing Collaborative Filtering...');
        $this->generateCollaborativeRecommendations();

        // 2. Content-based Filtering (Same Category fallback)
        $this->info('Processing Content-based Filtering...');
        $this->generateContentRecommendations();

        $this->info('Recommendation generation completed successfully.');
    }

    private function generateCollaborativeRecommendations()
    {
        // Get all orders with their items
        $orders = Order::with('orderItems')->where('status', '!=', 'cancelled')->get();

        $itemPairs = [];
        $itemCounts = [];

        foreach ($orders as $order) {
            $items = $order->orderItems->pluck('menu_id')->unique()->toArray();
            
            // Count individual occurrences
            foreach ($items as $item) {
                if (!isset($itemCounts[$item])) {
                    $itemCounts[$item] = 0;
                }
                $itemCounts[$item]++;
            }

            // Count pair occurrences
            for ($i = 0; $i < count($items); $i++) {
                for ($j = $i + 1; $j < count($items); $j++) {
                    $itemA = $items[$i];
                    $itemB = $items[$j];

                    // Store both directions
                    $this->incrementPair($itemPairs, $itemA, $itemB);
                    $this->incrementPair($itemPairs, $itemB, $itemA);
                }
            }
        }

        // Calculate scores and save
        $insertData = [];
        foreach ($itemPairs as $menuId => $relatedItems) {
            foreach ($relatedItems as $recommendedMenuId => $coOccurrences) {
                // Confidence score: P(B|A) = Count(A and B) / Count(A)
                $score = $coOccurrences / $itemCounts[$menuId];

                // Only save significant relationships (e.g. at least 2 co-occurrences or > 0.1 score)
                if ($coOccurrences >= 2 || $score > 0.1) {
                    $insertData[] = [
                        'menu_id' => $menuId,
                        'recommended_menu_id' => $recommendedMenuId,
                        'algorithm' => 'collaborative',
                        'score' => $score,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
        }

        if (!empty($insertData)) {
            // Chunk inserts to handle large datasets
            foreach (array_chunk($insertData, 500) as $chunk) {
                MenuRecommendation::insert($chunk);
            }
        }
    }

    private function incrementPair(&$pairs, $itemA, $itemB)
    {
        if (!isset($pairs[$itemA])) {
            $pairs[$itemA] = [];
        }
        if (!isset($pairs[$itemA][$itemB])) {
            $pairs[$itemA][$itemB] = 0;
        }
        $pairs[$itemA][$itemB]++;
    }

    private function generateContentRecommendations()
    {
        $menus = Menu::where('is_available', true)->get();
        $insertData = [];

        foreach ($menus as $menu) {
            // Find other menus in the same category
            $similarMenus = Menu::where('category_id', $menu->category_id)
                ->where('id', '!=', $menu->id)
                ->where('is_available', true)
                ->get();

            foreach ($similarMenus as $similarMenu) {
                // Check if a collaborative recommendation already exists to avoid dupes/conflicts
                $exists = MenuRecommendation::where('menu_id', $menu->id)
                    ->where('recommended_menu_id', $similarMenu->id)
                    ->exists();

                if (!$exists) {
                    $insertData[] = [
                        'menu_id' => $menu->id,
                        'recommended_menu_id' => $similarMenu->id,
                        'algorithm' => 'content',
                        'score' => 0.5, // Base score for same category
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
        }

        if (!empty($insertData)) {
            foreach (array_chunk($insertData, 500) as $chunk) {
                MenuRecommendation::insert($chunk);
            }
        }
    }
}
