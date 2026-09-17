<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            [
                'email' => 'admin@test.com',
            ],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password123'),
                'phone' => '03001234567',
                'avatar' => 'avatars/admin.jpg',
                'status' => true,
            ]
        );

        if (!$admin->hasRole('admin')) {
            $admin->assignRole('admin');
        }
    }
}