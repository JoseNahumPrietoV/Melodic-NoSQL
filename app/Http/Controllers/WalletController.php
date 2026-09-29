<?php

namespace App\Http\Controllers;

use App\Models\Earning;
use App\Models\Expense;
use App\Models\Royalty;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WalletController extends Controller
{
    /**
     * Maneja el retiro de fondos para cualquier usuario.
     *
     * @return RedirectResponse
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
                'from'    => $royalty->royalties_id,
                'user_id' => $user->user_id,
            ]);

            Earning::create([
                'amount' => $platformShare,
                'state'  => 'pagado',
                'at'     => now(),
                'comision' => $royalty->royalties_id,
            ]);

            $royalty->amount = 0;
            $royalty->last_payment = now();
            $royalty->save();
        });

        return back()->with('success', '¡Retiro procesado! Se ha transferido el 70% de tus regalías.');
    }
}
