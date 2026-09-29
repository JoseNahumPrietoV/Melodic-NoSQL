<?php

namespace App\Http\Controllers;

use App\Models\Song;
use App\Models\Album;
use App\Models\Royalty;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

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
            'album_id'   => 'nullable|exists:albums,album_id',
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

    /**
     * Registra una reproducción y genera la regalía ($).
     */
    public function registerPlay(Request $request, Song $song)
    {
        DB::transaction(function () use ($song) {
            $song->increment('plays');
            $paymentPerPlay = 0.15;

            $artist = $song->album->artist;
            $recipientId = $artist->label ? $artist->label : $artist->user_id;

            $royalty = Royalty::firstOrCreate(
                ['recipient_id' => $recipientId],
                ['amount' => 0]
            );

            $royalty->amount += $paymentPerPlay;
            $royalty->save();
        });

        return response()->json(['success' => true]);
    }
}
