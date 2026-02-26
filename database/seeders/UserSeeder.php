<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Super Admin
        User::create([
            'name' => 'Super Admin',
            'email' => 'admin@globalintermedia.online',
            'password' => Hash::make('admin123'),
            'role' => 'admin',
            'is_active' => true
        ]);

        // Admin Jakarta
        User::create([
            'name' => 'Admin Jakarta',
            'email' => 'admin.jakarta@globalintermedia.online',
            'password' => Hash::make('admin123'),
            'role' => 'admin',
            'is_active' => true
        ]);

        // Admin Bandung
        User::create([
            'name' => 'Admin Bandung',
            'email' => 'admin.bandung@globalintermedia.online',
            'password' => Hash::make('admin123'),
            'role' => 'admin',
            'is_active' => true
        ]);

        // Admin Surabaya
        User::create([
            'name' => 'Admin Surabaya',
            'email' => 'admin.surabaya@globalintermedia.online',
            'password' => Hash::make('admin123'),
            'role' => 'admin',
            'is_active' => true
        ]);

        // Regular Users
        User::create([
            'name' => 'User HR',
            'email' => 'hr@globalintermedia.online',
            'password' => Hash::make('user123'),
            'role' => 'user',
            'is_active' => true
        ]);

        User::create([
            'name' => 'User Supervisor',
            'email' => 'supervisor@globalintermedia.online',
            'password' => Hash::make('user123'),
            'role' => 'user',
            'is_active' => true
        ]);

        User::create([
            'name' => 'User Manager',
            'email' => 'manager@globalintermedia.online',
            'password' => Hash::make('user123'),
            'role' => 'user',
            'is_active' => true
        ]);

        // User tidak aktif (testing)
        User::create([
            'name' => 'User Inactive',
            'email' => 'inactive@test.com',
            'password' => Hash::make('password'),
            'role' => 'user',
            'is_active' => false
        ]);

        // Test accounts
        User::create([
            'name' => 'Admin Test',
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'is_active' => true
        ]);

        User::create([
            'name' => 'User Test',
            'email' => 'user@test.com',
            'password' => Hash::make('password'),
            'role' => 'user',
            'is_active' => true
        ]);
    }
}