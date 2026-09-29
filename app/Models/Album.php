<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Album extends Model
{
    use HasFactory;

    // 1. Definir la llave primaria (porque no es 'id')
    protected $primaryKey = 'album_id';

    // 2. Desactivar timestamps si tu tabla no tiene created_at/updated_at
    // (Según el error que vimos antes, tu tabla no los tiene)
    public $timestamps = false;

    // 3. ¡ESTO ES LO QUE FALTABA! Permitir asignación masiva
    protected $fillable = [
        'title',
        'user_id',
        'path',
    ];

    /**
     * Relación: Un álbum pertenece a un artista (User)
     */
    public function artist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    /**
     * Relación: Un álbum tiene muchas canciones
     */
    public function songs(): HasMany
    {
        return $this->hasMany(Song::class, 'album_id', 'album_id');
    }
}
