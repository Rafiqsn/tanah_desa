<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class UsersTableSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'kepala@gmail.com'],
            ['name' => 'Kepala Desa', 'role' => 'kepala', 'password' => 'password123']
        );

        User::updateOrCreate(
            ['email' => 'staff@gmail.com'],
            ['name' => 'Sekretaris Desa', 'role' => 'staff', 'password' => 'password123']
        );
    }
}
