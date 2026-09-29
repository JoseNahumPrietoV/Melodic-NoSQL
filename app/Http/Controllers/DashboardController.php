<?php

namespace App\Http\Controllers;

use App\Models\Earning;
use App\Models\Expense;
use App\Models\Royalty;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Song;
use App\Models\Playlist;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;

class DashboardController extends Controller
{
    /**
     * Regresa el dashboard de usuarios
     *
     * @return \Illuminate\View\View
     */
    public function user()
    {
        $songs = Song::with('album.artist')
            ->inRandomOrder()
            ->take(6)
            ->get();

        $user = Auth::user();
        $playlists = $user->playlists()->withCount('songs')->latest()->get();

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

        $albums = $artist->albums()->withCount('songs')->get();

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
            $songsQuery->where('name', 'LIKE', "%{$query}%")
                ->orWhereHas('album.artist', function ($q) use ($query) {
                    $q->where('username', 'LIKE', "%{$query}%");
                });
        } else {
            $songsQuery->latest();
        }

        $songs = $songsQuery->get();

        return view('dashboards.explore', [
            'songs' => $songs,
            'query' => $query
        ]);
    }

    /**
     * Logica de retiro para artistas independientes
     *
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Throwable
     */
    public function withdraw()
    {
        $user = Auth::user();

        $royalty = Royalty::where('recipient_id', $user->user_id)->first();

        if (!$royalty || $royalty->amount <= 0) {
            return back()->with('error', 'No tienes fondos disponibles para retirar.');
        }

        DB::transaction(function () use ($royalty, $user) {
            $totalAmount = $royalty->amount;
            $platformShare = $totalAmount * 0.30;
            $userPayout = $totalAmount * 0.70;

            Expense::create([
                'amount' => $userPayout,
                'state'  => 'pagado',
                'at'     => now(),
            ]);

            Earning::create([
                'amount' => $platformShare,
                'state'  => 'pagado',
                'at'     => now(),
            ]);

            $royalty->amount = 0;
            $royalty->last_payment = now();
            $royalty->save();
        });

        return back()->with('success', '¡Retiro exitoso! Los fondos se han transferido.');
    }
}
