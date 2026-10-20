<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MongoDB\Laravel\Eloquent\Model;

class Expense extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';

    protected $attributes = [
        'state' => 'pagado',
        'from' => null,
        'user_id' => null,
    ];

    protected $fillable = [
        'amount',
        'state',
        'at',
        'user_id',
        'from',
    ];

    protected $casts = [
        'at' => 'datetime',
        'amount' => 'decimal:2',
    ];

    protected function expenseId(): Attribute
    {
        return Attribute::get(fn () => $this->getKey());
    }

    /**
     * Relación: Un gasto (retiro) pertenece a un usuario (Artista o Label)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
