<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(SuperAdminSeeder::class);

        if (app()->environment('local')) {
            User::firstOrCreate(
                ['email' => 'test@example.com'],
                User::factory()->make(['name' => 'Test User'])->only('name', 'password', 'email_verified_at'),
            );
        }
    }
}
