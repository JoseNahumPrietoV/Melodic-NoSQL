<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class RegisterController extends Controller
{
    /**
     * Logica de registro
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'min:6'],
            'account_type' => ['required']
        ]);

        User::create([
            'username' => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'role'     => $request->account_type,
        ]);

        if (!auth()->attempt($request->only('email', 'password'))) {
            return redirect()->route('login')
                ->with('error', '¡Registro completado! Ocurrió un error al iniciar sesión automáticamente. Por favor, intenta ingresar.');
        }

        $user = Auth::user();
        $successMessage = '¡Bienvenido! Tu registro fue exitoso.';

        return match($user->role) {
            'user'   => redirect()->route('dashboard.user')->with('success', $successMessage),
            'artist' => redirect()->route('dashboard.artist')->with('success', $successMessage),
            default  => redirect('/login')->with('success', $successMessage),
        };
    }
}
