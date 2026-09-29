<?php

namespace App\Http\Controllers;

use App\Models\Earning;
use App\Models\Expense;
use App\Models\Royalty;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class LabelController extends Controller
{
    /**
     * Crea un nuevo perfil de Artista vinculado a una Label.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function storeArtist(Request $request)
    {
        $request->validate([
            'username' => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|min:6',
        ]);

        $label = Auth::user();

        User::create([
            'username'     => $request->username,
            'email'        => $request->email,
            'password'     => Hash::make($request->password),
            'role'         => 'artist',
            'label'        => $label->user_id,
            'is_suscribed' => true,
        ]);

        return back()->with('success', '¡Perfil de artista creado exitosamente!');
    }

    /**
     * Logica de retiro para labels
     *
     * @return RedirectResponse
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
                'amount'  => $userPayout,
                'state'   => 'pagado',
                'at'      => now(),
                'user_id' => $user->user_id,
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

        return back()->with('success', '¡Retiro procesado! Se ha transferido el 70% de tus regalías.');
    }
}
