<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        schema::create('albums', function (Blueprint $table) {
            $table->id('album_id');
            $table->string('title');
            $table->text('path')->nullable();
            $table->foreignUuid('user_id')->references('user_id')->on('users');
            $table->timestamps();
        });

        Schema::create('songs', function (Blueprint $table) {
            $table->id('song_id');
            $table->string('name');
            $table->unsignedInteger('length')->default(0);
            $table->unsignedBigInteger('plays');
            $table->text('file_path');
            $table->text('image_path')->nullable();
            $table->foreignId('album_id')->references('album_id')->on('albums');
            $table->timestamps();
        });

        Schema::create('plays', function (Blueprint $table) {
           $table->id('play_id');
           $table->foreignId('song_id')->references('song_id')->on('songs');
           $table->foreignUuid('user_id')->references('user_id')->on('users');
           $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('songs');
        Schema::dropIfExists('albums');
        Schema::dropIfExists('plays');
    }
};
