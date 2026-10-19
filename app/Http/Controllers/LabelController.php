<?php

namespace App\Http\Controllers;

use App\Services\WalletService;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

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
            'email'    => ['required', 'email', Rule::unique(User::class, 'email')],
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


    public function withdraw(WalletService $wallet)
    {
        if (! $wallet->withdraw(Auth::user())) {
            return back()->with('error', 'No tienes fondos disponibles para retirar.');
        }

        return back()->with('success', '¡Retiro procesado! Se ha transferido el 70% de tus regalías.');
    }
}
