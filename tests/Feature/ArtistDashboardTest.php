<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Models\Song;
use App\Models\User;
use Tests\Concerns\RefreshMongoDatabase;
use Tests\TestCase;

class ArtistDashboardTest extends TestCase
{
    use RefreshMongoDatabase;

    public function test_artist_dashboard_lists_albums_with_their_song_count(): void
    {
        $artist = User::factory()->artist()->create();
        $album = Album::factory()->for($artist, 'artist')->create();
        Song::factory()->count(3)->for($album)->create();

        $this->actingAs($artist)
            ->get(route('dashboard.artist'))
            ->assertOk()
            ->assertViewHas('albums', fn ($albums) => $albums->sole()->songs_count === 3);
    }

    public function test_album_page_shows_its_songs(): void
    {
        $album = Album::factory()->create();
        $song = Song::factory()->for($album)->create(['name' => 'Canción del álbum']);

        $this->actingAs(User::factory()->subscribed()->create())
            ->get(route('album.show', $album))
            ->assertOk()
            ->assertSee($song->name);
    }
}
