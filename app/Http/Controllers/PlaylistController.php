<?php

namespace App\Http\Controllers;

use App\Models\Playlist;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Models\Song;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

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

        $playlist->load('user');

        $query = Song::with('album.artist')
            ->whereNotIn('_id', $playlist->songIds());

        if ($request->has('search')) {
            $availableSongs = $query->matching((string) $request->input('search'))->get();
        } else {
            $availableSongs = collect();
        }

        return view('playlists.show', [
            'playlist'       => $playlist,
            'songs'          => $playlist->songModels(),
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
            'song_id' => ['required', Rule::exists(Song::class, '_id')],
        ]);

        if ($playlist->user_id !== auth()->id()) {
            abort(403);
        }

        $songId = $request->input('song_id');

        if ($playlist->addSong($songId)) {
            return back()->with('success', '¡Canción añadida!');
        }

        if (in_array($songId, $playlist->songIds(), true)) {
            return back()->with('info', 'Esa canción ya está en tu playlist.');
        }

        return back()->with('error', 'La playlist ya tiene el máximo de '.Playlist::MAX_SONGS.' canciones.');
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
            ->latest()
            ->get();

        return view('playlists.index', [
            'playlists' => $playlists
        ]);
    }
}
