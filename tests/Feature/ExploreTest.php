<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Models\Song;
use App\Models\User;
use Tests\Concerns\RefreshMongoDatabase;
use Tests\TestCase;

class ExploreTest extends TestCase
{
    use RefreshMongoDatabase;

    public function test_explore_without_a_query_lists_every_song(): void
    {
        Song::factory()->count(3)->create();

        $this->explore()
            ->assertOk()
            ->assertViewHas('songs', fn ($songs) => $songs->count() === 3);
    }

    public function test_explore_finds_songs_by_name_ignoring_case(): void
    {
        $match = Song::factory()->create(['name' => 'Noche Estrellada']);
        Song::factory()->create(['name' => 'Mañana gris']);

        $this->explore('estrella')
            ->assertViewHas('songs', fn ($songs) => $songs->pluck('song_id')->all() === [$match->song_id]);
    }

    public function test_explore_finds_songs_by_artist_username(): void
    {
        $artist = User::factory()->artist()->create(['username' => 'Carla Ríos']);
        $match = Song::factory()->for(Album::factory()->for($artist, 'artist'))->create(['name' => 'Tema uno']);
        Song::factory()->create(['name' => 'Tema dos']);

        $this->explore('carla')
            ->assertViewHas('songs', fn ($songs) => $songs->pluck('song_id')->all() === [$match->song_id]);
    }

    public function test_explore_treats_regex_characters_as_plain_text(): void
    {
        $match = Song::factory()->create(['name' => 'Tema (en vivo)']);
        Song::factory()->create(['name' => 'Tema en vivo']);

        $this->explore('(en vivo')
            ->assertOk()
            ->assertViewHas('songs', fn ($songs) => $songs->pluck('song_id')->all() === [$match->song_id]);

        $this->explore('.*')
            ->assertViewHas('songs', fn ($songs) => $songs->isEmpty());
    }

    public function test_listener_dashboard_shows_six_random_songs(): void
    {
        Song::factory()->count(8)->create();

        $this->actingAs(User::factory()->subscribed()->create())
            ->get(route('dashboard.user'))
            ->assertOk()
            ->assertViewHas('songs', fn ($songs) => $songs->count() === 6 && $songs->every(fn (Song $song) => $song->album->artist !== null));
    }

    private function explore(?string $query = null)
    {
        return $this->actingAs(User::factory()->subscribed()->create())
            ->get(route('explore', array_filter(['q' => $query])));
    }
}
