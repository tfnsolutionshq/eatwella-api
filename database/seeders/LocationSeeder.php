<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\State;
use App\Models\Zone;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $state = State::firstOrCreate(
            ['name' => 'Anambra'],
            ['code' => 'AN']
        );

        $city = City::firstOrCreate(
            ['state_id' => $state->id, 'name' => 'Awka']
        );

        $zones = [
            [
                'name' => 'Nnamdi Azikiwe University (NAU)',
                'is_active' => true,
                'delivery_fee' => 500.00,
                'sort_order' => 1,
            ],
            [
                'name' => 'Aroma',
                'is_active' => false,
                'delivery_fee' => 700.00,
                'sort_order' => 2,
            ],
            [
                'name' => 'Ifite',
                'is_active' => false,
                'delivery_fee' => 800.00,
                'sort_order' => 3,
            ],
            [
                'name' => 'Tempsite',
                'is_active' => false,
                'delivery_fee' => 600.00,
                'sort_order' => 4,
            ],
        ];

        foreach ($zones as $zoneData) {
            Zone::updateOrCreate(
                ['city_id' => $city->id, 'name' => $zoneData['name']],
                $zoneData
            );
        }
    }
}
