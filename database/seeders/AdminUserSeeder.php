<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Seed the initial admin user for production.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@drgo.office'],
            [
                'username' => 'admin',
                'display_name' => '관리자',
                'password' => Hash::make('changeme1234!'),
                'role' => 'master',
                'is_active' => true,
            ]
        );
    }
}
