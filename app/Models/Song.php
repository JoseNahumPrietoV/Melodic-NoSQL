<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MongoDB\Laravel\Eloquent\Model;

class Song extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';

    protected $fillable = [
        'name',
        'album_id',
        'file_path',
        'image_path',
        'plays',
    ];

    protected $attributes = [
        'length' => 0,
        'plays' => 0,
        'image_path' => null,
    ];

    protected $casts = [
        'length' => 'integer',
        'plays' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (Song $song) {
            if ($song->isDirty('album_id')) {
                $song->snapshotArtist();
            }
        });
    }

    protected function songId(): Attribute
    {
        return Attribute::get(fn () => $this->getKey());
    }

    /**
     * Relación: Una canción pertenece a un álbum
     */
    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class, 'album_id');
    }

    private function snapshotArtist(): void
    {
        $artist = Album::findOrFail($this->album_id)->artist;

        $this->artist = ['user_id' => $artist->user_id, 'username' => $artist->username];
    }
}
