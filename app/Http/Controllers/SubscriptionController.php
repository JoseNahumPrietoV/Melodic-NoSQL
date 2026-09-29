<?php

namespace App\Http\Controllers;

use App\Models\Earning;
use App\Models\Subscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class SubscriptionController extends Controller
{
    /**
     * Muestra la lista de planes de suscripción disponibles.
     */
    public function index()
    {
        $plans = Subscription::all();
        return view('subscriptions.index', compact('plans'));
    }

    /**
     * Procesa la "compra" de una suscripción.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function store(Request $request)
    {
        $request->validate([
            'plan_id' => 'required|exists:subscriptions,id',
        ]);

        $user = Auth::user();

        $plan = Subscription::findOrFail($request->plan_id);

        if ($user->is_suscribed && $user->fin_subscription && $user->fin_subscription->isFuture()) {
            $startDate = $user->fin_subscription;
        } else {
            $startDate = Carbon::now();
        }

        $endDate = $startDate->copy()->addMonths($plan->months);

        Earning::create([
            'amount' => $plan->cost,
            'state'  => 'pagado',
            'at'     => now(),
            'subscription' => $plan->id,
        ]);

        $user->is_suscribed = true;
        $user->subscription_id = $plan->id;
        $user->fin_subscription = $endDate;
        $user->save();

        return redirect()->route('dashboard.user')
            ->with('success', '¡Suscripción activada! Tu acceso Premium es válido hasta el ' . $endDate->format('d/m/Y'));
    }
}
