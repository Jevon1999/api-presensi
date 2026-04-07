<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@globalintermedia.online'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('admin123'),
                'role' => 'admin',
                'is_active' => true
            ]
        );

        // Admin Jakarta
        User::updateOrCreate(
            ['email' => 'admin.jakarta@globalintermedia.online'],
            [
                'name' => 'Admin Jakarta',
                'password' => Hash::make('admin123'),
                'role' => 'admin',
                'is_active' => true
            ]
        );

        // Admin Bandung
        User::updateOrCreate(
            ['email' => 'admin.bandung@globalintermedia.online'],
            [
                'name' => 'Admin Bandung',
                'password' => Hash::make('admin123'),
                'role' => 'admin',
                'is_active' => true
            ]
        );

        // Admin Surabaya
        User::updateOrCreate(
            ['email' => 'admin.surabaya@globalintermedia.online'],
            [
                'name' => 'Admin Surabaya',
                'password' => Hash::make('admin123'),
                'role' => 'admin',
                'is_active' => true
            ]
        );

        // Regular Users
        User::updateOrCreate(
            ['email' => 'hr@globalintermedia.online'],
            [
                'name' => 'User HR',
                'password' => Hash::make('user123'),
                'role' => 'user',
                'is_active' => true
            ]
        );

        User::updateOrCreate(
            ['email' => 'supervisor@globalintermedia.online'],
            [
                'name' => 'User Supervisor',
                'password' => Hash::make('user123'),
                'role' => 'user',
                'is_active' => true
            ]
        );

        User::updateOrCreate(
            ['email' => 'manager@globalintermedia.online'],
            [
                'name' => 'User Manager',
                'password' => Hash::make('user123'),
                'role' => 'user',
                'is_active' => true
            ]
        );

        // User tidak aktif (testing)
        User::updateOrCreate(
            ['email' => 'inactive@test.com'],
            [
                'name' => 'User Inactive',
                'password' => Hash::make('password'),
                'role' => 'user',
                'is_active' => false
            ]
        );

        // Test accounts
        User::updateOrCreate(
            ['email' => 'admin@test.com'],
            [
                'name' => 'Admin Test',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'is_active' => true
            ]
        );

        User::updateOrCreate(
            ['email' => 'user@test.com'],
            [
                'name' => 'User Test',
                'password' => Hash::make('password'),
                'role' => 'user',
                'is_active' => true
            ]
        );
    }
}