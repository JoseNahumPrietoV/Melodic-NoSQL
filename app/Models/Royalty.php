<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Royalty extends Model
{
    use HasFactory;

    protected $primaryKey = 'royalties_id';

    protected $fillable = ['amount', 'last_payment', 'recipient_id'];
}
