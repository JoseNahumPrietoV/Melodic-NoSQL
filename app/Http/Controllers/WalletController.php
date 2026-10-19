<?php

namespace App\Http\Controllers;

use App\Services\WalletService;
use Illuminate\Support\Facades\Auth;

class WalletController extends Controller
{
    public function withdraw(WalletService $wallet)
    {
        if (! $wallet->withdraw(Auth::user())) {
            return back()->with('error', 'No tienes fondos disponibles para retirar.');
        }

        return back()->with('success', '¡Retiro procesado! Se ha transferido el 70% de tus regalías.');
    }
}
