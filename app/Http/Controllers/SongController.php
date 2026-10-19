<?php

namespace App\Http\Controllers;

use App\Models\Song;
use App\Models\Album;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class SongController extends Controller
{
    /**
     * Almacena una nueva canción.
     */
    public function store(Request $request)
    {
        if ($request->album_id === '') {
            $request->merge(['album_id' => null]);
        }

        $request->validate([
            'name'       => 'required|string|max:255',
            'album_id'   => ['nullable', Rule::exists(Album::class, '_id')],
            'audio_file' => 'required|file|mimes:mp3,wav,ogg,mpga,aac,m4a|max:20480',
            'cover_file' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        $audioPath = $request->file('audio_file')->store('songs/audio', 'public');

        $imagePath = null;
        if ($request->hasFile('cover_file')) {
            $imagePath = $request->file('cover_file')->store('songs/images', 'public');
        }

        $albumId = $request->album_id;

        if (!$albumId) {
            $newAlbum = Album::create([
                'title'   => $request->name,
                'user_id' => Auth::id(),
                'path'    => $imagePath,
            ]);

            $albumId = $newAlbum->album_id;
        }

        Song::create([
            'name'       => $request->name,
            'album_id'   => $albumId,
            'file_path'  => $audioPath,
            'image_path' => $imagePath,
            'plays'      => 0,
        ]);

        return redirect()->route('dashboard.artist')
            ->with('success', '¡Canción subida con éxito!');
    }


    public function registerPlay(Song $song, WalletService $wallet)
    {
        $wallet->registerPlay($song);

        return response()->json(['success' => true]);
    }
}
