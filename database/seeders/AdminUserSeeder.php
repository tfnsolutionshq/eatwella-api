<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@eatwella.ng'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('#Eatmin2026'),
                'role' => 'admin',
            ]
        );
    }
}
