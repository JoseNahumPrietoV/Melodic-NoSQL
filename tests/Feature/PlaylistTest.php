<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Models\Playlist;
use App\Models\Song;
use App\Models\User;
use MongoDB\BSON\ObjectId;
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

        $this->assertSame(1, $playlist->refresh()->songs_count);
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

        $this->assertSame(0, $playlist->refresh()->songs_count);
    }

    public function test_playlist_search_finds_songs_by_name_or_artist_outside_the_playlist(): void
    {
        $playlist = Playlist::factory()->create();
        $alreadyAdded = Song::factory()->create(['name' => 'Luna llena']);
        $playlist->addSong($alreadyAdded->song_id);
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

    public function test_a_full_playlist_rejects_new_songs(): void
    {
        $songs = collect(range(1, Playlist::MAX_SONGS))
            ->map(fn () => ['song_id' => (string) new ObjectId, 'added_at' => now()])
            ->all();
        $playlist = Playlist::factory()->create();
        $playlist->songs = $songs;
        $playlist->save();
        $song = Song::factory()->create();

        $this->actingAs($playlist->user)
            ->post(route('playlist.addSong', $playlist), ['song_id' => $song->song_id])
            ->assertSessionHas('error');

        $this->assertSame(Playlist::MAX_SONGS, $playlist->refresh()->songs_count);
    }

    public function test_playlist_shows_its_songs_in_the_order_they_were_added(): void
    {
        $playlist = Playlist::factory()->create();
        $songs = Song::factory()->count(3)->create();

        foreach ($songs->reverse() as $song) {
            $playlist->addSong($song->song_id);
        }

        $this->actingAs($playlist->user)
            ->get(route('playlist.show', $playlist))
            ->assertOk()
            ->assertViewHas('songs', fn ($shown) => $shown->pluck('song_id')->all() === $songs->reverse()->pluck('song_id')->values()->all());
    }
}
