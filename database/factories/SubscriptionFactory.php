<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Subscription>
 */
class SubscriptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Mensual', 'Trimestral', 'Anual']),
            'cost' => 99.00,
            'months' => 1,
        ];
    }
}
