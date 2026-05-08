<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DefaultCustomerSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'eatwella@gmail.com'],
            [
                'name'     => 'Eatwella Customer',
                'phone'    => '09017777701',
                'password' => 'eatwella_default_customer',
                'role'     => 'customer',
            ]
        );
    }
}
