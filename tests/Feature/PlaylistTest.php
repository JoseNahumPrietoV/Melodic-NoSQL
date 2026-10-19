<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Models\Playlist;
use App\Models\Song;
use App\Models\User;
use Tests\Concerns\RefreshMongoDatabase;
use Tests\TestCase;

class PlaylistTest extends TestCase
{
    use RefreshMongoDatabase;

    public function test_listener_creates_a_playlist(): void
    {
        $listener = User::factory()->subscribed()->create();

        $this->actingAs($listener)
            ->post(route('playlist.store'), ['name' => 'Para correr'])
            ->assertRedirect(route('dashboard.user'));

        $this->assertDatabaseHas(Playlist::class, ['name' => 'Para correr', 'user_id' => $listener->user_id]);
    }

    public function test_adding_a_song_twice_keeps_a_single_entry(): void
    {
        $playlist = Playlist::factory()->create();
        $song = Song::factory()->create();

        $this->actingAs($playlist->user)
            ->post(route('playlist.addSong', $playlist), ['song_id' => $song->song_id])
            ->assertSessionHas('success');

        $this->actingAs($playlist->user)
            ->post(route('playlist.addSong', $playlist), ['song_id' => $song->song_id])
            ->assertSessionHas('info');

        $this->assertSame(1, $playlist->songs()->count());
    }

    public function test_only_the_owner_can_view_or_change_a_playlist(): void
    {
        $playlist = Playlist::factory()->create();
        $song = Song::factory()->create();
        $stranger = User::factory()->subscribed()->create();

        $this->actingAs($stranger)
            ->get(route('playlist.show', $playlist))
            ->assertForbidden();

        $this->actingAs($stranger)
            ->post(route('playlist.addSong', $playlist), ['song_id' => $song->song_id])
            ->assertForbidden();

        $this->assertSame(0, $playlist->songs()->count());
    }

    public function test_playlist_search_finds_songs_by_name_or_artist_outside_the_playlist(): void
    {
        $playlist = Playlist::factory()->create();
        $alreadyAdded = Song::factory()->create(['name' => 'Luna llena']);
        $playlist->songs()->attach($alreadyAdded);
        $byName = Song::factory()->create(['name' => 'Luna nueva']);
        $byArtist = Song::factory()
            ->for(Album::factory()->for(User::factory()->artist()->state(['username' => 'Los Lunares']), 'artist'))
            ->create(['name' => 'Otra canción']);
        Song::factory()->create(['name' => 'Sol de mediodía']);

        $this->actingAs($playlist->user)
            ->get(route('playlist.show', ['playlist' => $playlist, 'search' => 'lun']))
            ->assertOk()
            ->assertViewHas('availableSongs', fn ($songs) => $songs->pluck('song_id')->sort()->values()->all()
                === collect([$byName->song_id, $byArtist->song_id])->sort()->values()->all());
    }
}
