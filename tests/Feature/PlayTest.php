<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Models\Royalty;
use App\Models\Song;
use App\Models\User;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\Process;
use Tests\Concerns\RefreshMongoDatabase;
use Tests\TestCase;

class PlayTest extends TestCase
{
    use RefreshMongoDatabase;

    public function test_play_counts_the_song_and_pays_the_independent_artist(): void
    {
        $song = Song::factory()->create();

        $this->play($song)->assertOk()->assertJson(['success' => true]);

        $this->assertSame(1, (int) $song->refresh()->plays);
        $this->assertRoyaltyBalance($song->album->user_id, 0.15);
    }

    public function test_play_of_a_label_artist_pays_the_label(): void
    {
        $label = User::factory()->label()->create();
        $artist = User::factory()->artist()->create(['label' => $label->user_id]);
        $song = Song::factory()->for(Album::factory()->for($artist, 'artist'))->create();

        $this->play($song);

        $this->assertRoyaltyBalance($label->user_id, 0.15);
        $this->assertDatabaseMissing(Royalty::class, ['recipient_id' => $artist->user_id]);
    }

    public function test_consecutive_plays_accumulate_on_the_same_royalty(): void
    {
        $song = Song::factory()->create();

        $this->play($song);
        $this->play($song);

        $this->assertSame(2, (int) $song->refresh()->plays);
        $this->assertRoyaltyBalance($song->album->user_id, 0.30);
        $this->assertSame(1, Royalty::count());
    }

    public function test_concurrent_plays_add_exactly_fifteen_cents_each(): void
    {
        $song = Song::factory()->create();
        $plays = 20;

        $results = Process::pool(function (Pool $pool) use ($song, $plays) {
            foreach (range(1, $plays) as $attempt) {
                $pool->env([
                    'MONGODB_URI' => env('MONGODB_TEST_URI'),
                    'MONGODB_DATABASE' => config('database.connections.mongodb.database'),
                ])->command([PHP_BINARY, base_path('tests/Support/register-play.php'), $song->song_id]);
            }
        })->start()->wait();

        $this->assertTrue($results->collect()->every(fn ($result) => $result->successful()));
        $this->assertSame($plays, (int) $song->refresh()->plays);
        $this->assertSame('3.00', Royalty::where('recipient_id', $song->album->user_id)->firstOrFail()->amount);
    }

    private function play(Song $song)
    {
        return $this->actingAs(User::factory()->subscribed()->create())
            ->post(route('song.play', $song));
    }

    private function assertRoyaltyBalance(string $recipientId, float $expected): void
    {
        $royalty = Royalty::where('recipient_id', $recipientId)->firstOrFail();

        $this->assertEqualsWithDelta($expected, (float) $royalty->amount, 0.001);
    }
}
