<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;

class Earning extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';

    protected $attributes = [
        'state' => 'pagado',
        'subscription' => null,
        'comision' => null,
    ];

    protected $fillable = [
        'amount',
        'state',
        'at',
        'subscription',
        'comision',
    ];

    protected $casts = [
        'at' => 'datetime',
        'amount' => 'decimal:2',
    ];

    protected function earningId(): Attribute
    {
        return Attribute::get(fn () => $this->getKey());
    }
}
