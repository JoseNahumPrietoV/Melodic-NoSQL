<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use MongoDB\Laravel\Eloquent\Model;

class Album extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';

    public $timestamps = false;

    protected $fillable = [
        'title',
        'user_id',
        'path',
    ];

    protected function albumId(): Attribute
    {
        return Attribute::get(fn () => $this->getKey());
    }

    /**
     * Relación: Un álbum pertenece a un artista (User)
     */
    public function artist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relación: Un álbum tiene muchas canciones
     */
    public function songs(): HasMany
    {
        return $this->hasMany(Song::class, 'album_id');
    }
}
