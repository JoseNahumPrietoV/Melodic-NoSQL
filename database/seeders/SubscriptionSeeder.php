<?php

namespace Database\Seeders;

use App\Models\Subscription;
use Illuminate\Database\Seeder;

class SubscriptionSeeder extends Seeder
{
    public function run(): void
    {
        Subscription::create([
            'name' => 'Premium Mensual',
            'cost' => 99.00,
            'months' => 1
        ]);

        Subscription::create([
            'name' => 'Premium Anual',
            'cost' => 990.00,
            'months' => 12
        ]);
    }
}
