<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MongoDB\Laravel\Eloquent\Model;

class Playlist extends Model
{
    use HasFactory;

    public const MAX_SONGS = 500;

    protected $connection = 'mongodb';

    protected $fillable = [
        'name',
        'user_id',
        'path',
    ];

    protected $attributes = [
        'name' => 'Nueva playlist',
        'description' => null,
        'path' => null,
        'songs' => [],
    ];

    protected function playlistId(): Attribute
    {
        return Attribute::get(fn () => $this->getKey());
    }

    protected function songsCount(): Attribute
    {
        return Attribute::get(fn () => count($this->songs));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function songIds(): array
    {
        return array_column($this->songs, 'song_id');
    }

    public function songModels(): Collection
    {
        $position = array_flip($this->songIds());

        return Song::with('album.artist')
            ->whereIn('_id', $this->songIds())
            ->get()
            ->sortBy(fn (Song $song) => $position[$song->getKey()])
            ->values();
    }

    public function addSong(string $songId): bool
    {
        return static::where('_id', $this->getKey())
            ->where('songs.song_id', '!=', $songId)
            ->where('songs.'.(self::MAX_SONGS - 1), 'exists', false)
            ->push('songs', ['song_id' => $songId, 'added_at' => now()]) === 1;
    }
}
