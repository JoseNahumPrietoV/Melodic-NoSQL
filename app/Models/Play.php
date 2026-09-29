<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Play extends Model
{
    use HasFactory;

    protected $table = "plays";
    protected $fillable = [
        "song_id",
        "user_id"
    ];
    protected $primaryKey = "play_id";
}
