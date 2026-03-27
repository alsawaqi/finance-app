<?php

namespace Database\Seeders;

use App\Enums\UserAccountType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@example.com')],
            [
                'name' => env('ADMIN_NAME', 'System Admin'),
                'phone' => env('ADMIN_PHONE', '90000000'),
                'password' => Hash::make(env('ADMIN_PASSWORD', 'Admin@123456')),
                'account_type' => UserAccountType::ADMIN,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        if (! $admin->hasRole('admin')) {
            $admin->assignRole('admin');
        }
    }
}