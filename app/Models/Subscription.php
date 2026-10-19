<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;

class Subscription extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';

    protected $fillable = ['name', 'cost', 'months'];

    protected $casts = [
        'cost' => 'decimal:2',
        'months' => 'integer',
    ];
}
