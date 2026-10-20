<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Royalty>
 */
class RoyaltyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'amount' => 0,
            'last_payment' => null,
            'recipient_id' => User::factory()->artist(),
        ];
    }
}
