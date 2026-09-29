<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use HasFactory;

    protected $primaryKey = 'expense_id';

    protected $fillable = [
        'amount',
        'state',
        'at',
        'user_id',
        'from'
    ];

    protected $casts = [
        'at' => 'datetime',
        'amount' => 'decimal:2'
    ];

    /**
     * Relación: Un gasto (retiro) pertenece a un usuario (Artista o Label)
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
