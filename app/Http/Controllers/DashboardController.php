<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Royalty;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Song;
use App\Models\Playlist;
use Illuminate\Support\Facades\View;
use MongoDB\Collection;

class DashboardController extends Controller
{
    /**
     * Regresa el dashboard de usuarios
     *
     * @return \Illuminate\View\View
     */
    public function user()
    {
        $songs = Song::raw(fn (Collection $collection) => $collection->aggregate([['$sample' => ['size' => 6]]]))
            ->load('album.artist');

        $user = Auth::user();
        $playlists = $user->playlists()->latest()->get();

        return view('dashboards.user', [
            'songs'     => $songs,
            'playlists' => $playlists
        ]);
    }

    /**
     * Regresa el dashboard de artistas
     *
     * @return \Illuminate\View\View
     */
    public function artist()
    {
        $artist = Auth::user();

        $albums = $artist->albums()->with('songs')->get()
            ->each(fn ($album) => $album->setAttribute('songs_count', $album->songs->count()));

        $currentBalance = 0.00;
        $withdrawals = collect();

        if ($artist->label === null) {
            $royalty = Royalty::where('recipient_id', $artist->user_id)->first();
            $currentBalance = $royalty ? $royalty->amount : 0.00;

            $withdrawals = Expense::where('user_id', $artist->user_id)
                ->latest('at')
                ->take(5)
                ->get();
        }

        return view('dashboards.artist', [
            'artist'         => $artist,
            'albums'         => $albums,
            'currentBalance' => $currentBalance,
            'withdrawals'    => $withdrawals
        ]);
    }

    /**
     * Regresa el dashboard de label
     *
     * @return \Illuminate\View\View
     */
    public function label()
    {
        $label = Auth::user();

        $artists = User::where('label', $label->user_id)
            ->with('albums.songs')
            ->get();

        $totalPlays = 0;
        foreach ($artists as $artist) {
            foreach ($artist->albums as $album) {
                $totalPlays += $album->songs->sum('plays');
            }
        }

        $royalty = Royalty::where('recipient_id', $label->user_id)->first();
        $currentBalance = $royalty ? $royalty->amount : 0.00;

        $withdrawals = Expense::where('user_id', $label->user_id)
            ->latest('at')
            ->take(5)
            ->get();

        return view('dashboards.label', [
            'label'          => $label,
            'artists'        => $artists,
            'totalPlays'     => $totalPlays,
            'currentBalance' => $currentBalance,
            'withdrawals'    => $withdrawals
        ]);
    }

    /**
     * Regresa la vista de 'explorar' y realiza las busqueda de canciones
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function explore(Request $request)
    {
        $query = $request->input('q');

        $songsQuery = Song::with('album.artist');

        if ($query) {
            $songsQuery->matching($query);
        } else {
            $songsQuery->latest();
        }

        $songs = $songsQuery->get();

        return view('dashboards.explore', [
            'songs' => $songs,
            'query' => $query
        ]);
    }

}
