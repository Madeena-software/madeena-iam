<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = User::firstOrCreate([
            'email' => env('SUPER_ADMIN_EMAIL', 'admin@madeena.local'),
        ], [
            'name' => 'Super Admin',
            'password' => Hash::make(env('SUPER_ADMIN_PASSWORD', 'admin')),
        ]);

        $admin->assignRole('super_admin');
    }
}
