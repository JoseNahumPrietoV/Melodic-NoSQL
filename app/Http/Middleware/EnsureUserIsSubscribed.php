<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Carbon\Carbon;

class EnsureUserIsSubscribed
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (!$user || $user->role !== 'user') {
            return $next($request);
        }

        if ($user->is_suscribed && $user->fin_subscription && $user->fin_subscription->isPast()) {

            $user->is_suscribed = false;
            $user->fin_subscription = null;
            $user->subscription_id = null;
            $user->save();

            // 2. Redirigimos a la página de pago con un mensaje
            return redirect()->route('subscription.index')
                ->with('error', 'Tu suscripción ha vencido. Por favor, renueva para seguir escuchando.');
        }

        // Si después de la comprobación anterior NO está suscrito...
        if (!$user->is_suscribed) {
            return redirect()->route('subscription.index');
        }

        return $next($request);
    }
}
