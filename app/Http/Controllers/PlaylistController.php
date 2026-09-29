<?php

namespace App\Http\Controllers;

use App\Models\Playlist;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Models\Song;
use Illuminate\Support\Facades\Auth;

class PlaylistController extends Controller
{
    /**
     * Almacena una nueva playlist en la base de datos.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'cover' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $user = Auth::user();
        $path = null;

        if ($request->hasFile('cover')) {
            $path = $request->file('cover')->store('playlist_covers', 'public');
        }

        Playlist::create([
            'name'    => $request->name,
            'user_id' => $user->user_id,
            'path'    => $path,
        ]);

        return redirect()->route('dashboard.user')
            ->with('success', '¡Playlist creada con éxito!');
    }

    /**
     * Logica para mostrar playlists y busqueda de canciones para playlists
     *
     * @param Request $request
     * @param Playlist $playlist
     * @return \Illuminate\View\View
     */
    public function show(Request $request, Playlist $playlist)
    {
        if ($playlist->user_id !== auth()->id()) {
            abort(403, 'No tienes permiso.');
        }

        $playlist->load('songs.album.artist', 'user');

        $songIdsInPlaylist = $playlist->songs->pluck('song_id');

        $query = Song::with('album.artist')
            ->whereNotIn('song_id', $songIdsInPlaylist);

        if ($request->has('search')) {
            $searchTerm = $request->input('search');
            $query->where(function($q) use ($searchTerm) {
                $q->where('name', 'LIKE', "%{$searchTerm}%")
                    ->orWhereHas('album.artist', function($q2) use ($searchTerm) {
                        $q2->where('username', 'LIKE', "%{$searchTerm}%");
                    });
            });

            $availableSongs = $query->get();
        } else {
            $availableSongs = collect();
        }

        return view('playlists.show', [
            'playlist'       => $playlist,
            'availableSongs' => $availableSongs,
            'search'         => $request->input('search')
        ]);
    }

    /**
     * Logica para agregar canciones a playlists
     *
     * @param Request $request
     * @param Playlist $playlist
     * @return RedirectResponse
     */
    public function addSong(Request $request, Playlist $playlist)
    {
        $request->validate([
            'song_id' => 'required|exists:songs,song_id'
        ]);

        if ($playlist->user_id !== auth()->id()) {
            abort(403);
        }

        $songId = $request->input('song_id');

        if ($playlist->songs()->find($songId)) {
            return back()->with('info', 'Esa canción ya está en tu playlist.');
        }

        $playlist->songs()->attach($songId);

        return back()->with('success', '¡Canción añadida!');
    }

    /**
     * Regresa la vista de playlists
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $user = Auth::user();

        $playlists = $user->playlists()
            ->withCount('songs')
            ->latest()
            ->get();

        return view('playlists.index', [
            'playlists' => $playlists
        ]);
    }
}
