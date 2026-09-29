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
        $this->call([
            LabelSeeder::class,
            SubscriptionSeeder::class,
            AdminSeeder::class,
        ]);

        User::create([
            'username' => 'Jane Doe',
            'email' => 'user@melodic.com',
            'password' => Hash::make('password'),
            'role' => 'user',
        ]);

        User::create([
            'username' => 'John Doe',
            'email' => 'artist@melodic.com',
            'password' => Hash::make('password'),
            'role' => 'artist',
        ]);
    }
}
