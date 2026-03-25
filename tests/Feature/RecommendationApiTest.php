<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\MenuRecommendation;
use App\Models\Category;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class RecommendationApiTest extends TestCase
{
    // Deliberately NOT using RefreshDatabase to preserve user's DB state as requested

    public function test_can_fetch_cart_recommendations()
    {
        // Setup some basic data if it doesn't exist
        $category = Category::firstOrCreate(['name' => 'Test Category'], ['description' => 'Test']);
        
        $menu1 = Menu::firstOrCreate(
            ['name' => 'Menu Item 1'],
            ['category_id' => $category->id, 'description' => 'Test 1', 'price' => 10.00, 'is_available' => true]
        );
        $menu2 = Menu::firstOrCreate(
            ['name' => 'Menu Item 2'],
            ['category_id' => $category->id, 'description' => 'Test 2', 'price' => 15.00, 'is_available' => true]
        );

        // Seed a recommendation
        MenuRecommendation::firstOrCreate(
            ['menu_id' => $menu1->id, 'recommended_menu_id' => $menu2->id],
            ['algorithm' => 'hybrid', 'score' => 0.85]
        );

        // Test API Endpoint
        $response = $this->postJson('/api/recommendations/cart', [
            'menu_ids' => [$menu1->id]
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'data',
                     'ab_test_group',
                     'response_time_ms'
                 ]);
    }

    public function test_can_track_interaction()
    {
        $menu = Menu::first();

        $response = $this->postJson('/api/recommendations/track', [
            'recommended_menu_id' => $menu->id,
            'ab_test_group' => 'A',
            'action' => 'click'
        ]);

        $response->assertStatus(200)
                 ->assertJson(['message' => 'Interaction tracked successfully.']);
    }
}