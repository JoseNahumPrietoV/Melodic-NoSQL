<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Album>
 */
class AlbumFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->words(2, true),
            'user_id' => User::factory()->artist(),
            'path' => null,
        ];
    }
}
