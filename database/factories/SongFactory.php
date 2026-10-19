<?php

namespace Database\Factories;

use App\Models\Album;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Song>
 */
class SongFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'album_id' => Album::factory(),
            'file_path' => 'songs/audio/'.fake()->uuid().'.mp3',
            'image_path' => null,
            'plays' => 0,
        ];
    }
}
