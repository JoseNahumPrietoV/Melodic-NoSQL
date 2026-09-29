<?php

namespace App\Http\Controllers;

use App\Models\Album;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Factory;

class AlbumController extends Controller
{
    /**
     * Almacena un nuevo álbum en la base de datos.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'cover' => 'nullable|image|mimes:jpeg,png,jpg|max:2048', // 2MB Max
        ]);

        $artist = Auth::user();

        $path = null;

        if ($request->hasFile('cover')) {
            $path = $request->file('cover')->store('album_covers', 'public');
        }

        Album::create([
            'title'   => $request->title,
            'user_id' => $artist->user_id,
            'path'    => $path,
        ]);

        return redirect()->route('dashboard.artist')
            ->with('success', '¡Álbum creado con éxito!');
    }

    /**
     * Muestra los albumes solicitados
     *
     * @param Album $album
     * @return View
     */
    public function show(Album $album)
    {
        $album->load('artist', 'songs');

        return view('albums.show', [
            'album' => $album
        ]);
    }
}
