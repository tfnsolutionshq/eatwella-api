<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

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

        Setting::firstOrCreate(
            ['key' => 'delivery_fee'],
            ['value' => '0', 'description' => 'Flat delivery fee applied to delivery orders']
        );

        Setting::firstOrCreate(
            ['key' => 'restaurant_timezone'],
            ['value' => 'Africa/Lagos', 'description' => 'Timezone of the restaurant location']
        );

        Setting::firstOrCreate(
            ['key' => 'birthday_discount_percentage'],
            ['value' => '10', 'description' => 'Discount percentage sent to customers on their birthday']
        );

        Setting::firstOrCreate(
            ['key' => 'birthday_discount_type'],
            ['value' => 'menu', 'description' => 'Birthday discount type: menu, free_delivery, or both']
        );

        Setting::firstOrCreate(
            ['key' => 'birthday_free_delivery'],
            ['value' => '0', 'description' => 'Whether birthday customers get free delivery (1 = yes, 0 = no)']
        );

        Setting::firstOrCreate(
            ['key' => 'new_user_discount_type'],
            ['value' => 'none', 'description' => 'New user first order discount type: none, menu, free_delivery, or both']
        );

        Setting::firstOrCreate(
            ['key' => 'new_user_discount_percentage'],
            ['value' => '0', 'description' => 'Discount percentage for new user first order (used when type is menu or both)']
        );
    }
}
