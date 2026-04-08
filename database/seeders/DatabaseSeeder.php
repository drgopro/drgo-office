<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'username' => 'admin',
            'display_name' => '관리자',
            'email' => 'admin@drgo.pro',
            'password' => bcrypt('password'),
            'role' => 'master',
            'is_active' => 1,
        ]);
    }
}
