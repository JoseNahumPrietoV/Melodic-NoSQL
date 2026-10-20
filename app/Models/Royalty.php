<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;

class Royalty extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';

    protected $table = 'royalties';

    protected $fillable = ['amount', 'last_payment', 'recipient_id'];

    protected $attributes = [
        'amount' => 0,
        'last_payment' => null,
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'last_payment' => 'datetime',
    ];

    protected function royaltiesId(): Attribute
    {
        return Attribute::get(fn () => $this->getKey());
    }
}
