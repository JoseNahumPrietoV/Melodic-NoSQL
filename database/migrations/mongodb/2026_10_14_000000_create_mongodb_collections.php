<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

return new class extends Migration
{
    protected $connection = 'mongodb';

    private const LEGACY_COLLECTIONS = [
        'subscriptions',
        'albums',
        'songs',
        'playlists',
        'royalties',
        'earnings',
        'expenses',
    ];

    public function up(): void
    {
        Schema::create('users', function (Blueprint $collection) {
            $collection->unique('email');
            $collection->index('label');
            $collection->index(['role' => 1, 'created_at' => -1]);
        });

        Schema::create('subscriptions');

        Schema::create('albums', function (Blueprint $collection) {
            $collection->index('user_id');
        });

        Schema::create('songs', function (Blueprint $collection) {
            $collection->index('album_id');
            $collection->index('artist.user_id');
            $collection->index(['created_at' => -1]);
        });

        Schema::create('playlists', function (Blueprint $collection) {
            $collection->index(['user_id' => 1, 'created_at' => -1]);
        });

        Schema::create('royalties', function (Blueprint $collection) {
            $collection->unique('recipient_id');
        });

        Schema::create('earnings', function (Blueprint $collection) {
            $collection->index('at');
        });

        Schema::create('expenses', function (Blueprint $collection) {
            $collection->index(['user_id' => 1, 'at' => -1]);
        });

        foreach (self::LEGACY_COLLECTIONS as $name) {
            Schema::table($name, function (Blueprint $collection) {
                $collection->sparse_and_unique('legacy_id');
            });
        }
    }

    public function down(): void
    {
        foreach (['users', ...self::LEGACY_COLLECTIONS] as $name) {
            Schema::dropIfExists($name);
        }
    }
};
