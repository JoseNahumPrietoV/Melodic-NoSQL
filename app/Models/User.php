<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;
use MongoDB\Laravel\Auth\User as Authenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasUuids, Notifiable;

    protected $connection = 'mongodb';

    protected $table = 'users';

    public $incrementing = false;

    protected $attributes = [
        'role' => 'user',
        'is_suscribed' => false,
        'fin_subscription' => null,
        'subscription_id' => null,
        'label' => null,
        'path' => null,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'email',
        'password',
        'role',
        'label',
        'is_suscribed',
        'fin_subscription',
        'subscription_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_suscribed' => 'boolean',
        'fin_subscription' => 'date',
    ];

    protected function userId(): Attribute
    {
        return Attribute::get(fn () => $this->getKey());
    }

    /**
     * Relación: Un usuario (artista) tiene muchos álbumes
     *
     * @return HasMany
     */
    public function albums()
    {
        return $this->hasMany(Album::class, 'user_id');
    }

    /**
     * Relación: Un usuario (oyente) tiene muchas playlists
     */
    public function playlists(): HasMany
    {
        return $this->hasMany(Playlist::class, 'user_id');
    }
}
