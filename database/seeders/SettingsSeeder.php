<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Setting;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Setting::firstOrCreate(
            ['key' => 'loyalty_points_per_order'],
            ['value' => '10', 'description' => 'Points awarded per completed order']
        );

        Setting::firstOrCreate(
            ['key' => 'loyalty_min_points_redemption'],
            ['value' => '100', 'description' => 'Minimum points required to redeem']
        );

        Setting::firstOrCreate(
            ['key' => 'loyalty_conversion_rate'],
            ['value' => '1.0', 'description' => 'Value of 1 point in currency (e.g. 1 point = 1 NGN)']
        );
    }
}
