<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LabelController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\PlaylistController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\AlbumController;
use App\Http\Controllers\SongController;
use App\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('auth.login');
});

Route::get('/register', function () {
    return view('auth.register');
});

Route::post('/register', [RegisterController::class, 'store'])->name('register');

Route::get('/login', [LoginController::class, 'show'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.submit');

Route::middleware(['auth'])->group(function () {

    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    Route::get('/premium', [SubscriptionController::class, 'index'])->name('subscription.index');
    Route::post('/premium', [SubscriptionController::class, 'store'])->name('subscription.store');

    Route::middleware(['admin'])->group(function () {
        Route::get('/dashboard/admin', [AdminController::class, 'index'])
            ->middleware(['role:admin'])
            ->name('dashboard.admin');

        Route::post('/admin/create-label', [AdminController::class, 'storeLabel'])
            ->middleware(['role:admin'])
            ->name('admin.create_label');
    });

    Route::middleware(['subscribed'])->group(function () {

        Route::get('/dashboard/user', [DashboardController::class, 'user'])
            ->middleware(['role:user'])
            ->name('dashboard.user');

        Route::get('/dashboard/artist', [DashboardController::class, 'artist'])
            ->middleware(['role:artist'])
            ->name('dashboard.artist');

        Route::post('/album', [AlbumController::class, 'store'])
            ->middleware('role:artist')
            ->name('album.store');

        Route::post('/song', [SongController::class, 'store'])
            ->middleware('role:artist')
            ->name('song.store');

        Route::post('/song/{song}/play', [SongController::class, 'registerPlay'])
            ->name('song.play');

        Route::get('/album/{album}', [AlbumController::class, 'show'])
            ->name('album.show');

        Route::get('/playlists', [PlaylistController::class, 'index'])
            ->name('playlists.index');

        Route::post('/playlist', [PlaylistController::class, 'store'])
            ->middleware('role:user')
            ->name('playlist.store');

        Route::get('/playlist/{playlist}', [PlaylistController::class, 'show'])
            ->name('playlist.show');

        Route::post('/playlist/{playlist}/add-song', [PlaylistController::class, 'addSong'])
            ->name('playlist.addSong');

        Route::post('/withdraw', [WalletController::class, 'withdraw'])->name('wallet.withdraw');

        Route::get('/explore', [DashboardController::class, 'explore'])
            ->name('explore');

        Route::middleware(['role:label'])->group(function () {

            Route::get('/dashboard/label', [DashboardController::class, 'label'])
                ->name('dashboard.label');

            Route::post('/label/create-artist', [LabelController::class, 'storeArtist'])
                ->name('label.create_artist');

            Route::post('/label/withdraw', [LabelController::class, 'withdraw'])
                ->name('label.withdraw');
        });
    });
});
