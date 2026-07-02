<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Se l'utente ha impostato un PIN, blocca l'app finché non viene sbloccata
 * (flag in sessione). Nessun PIN = nessun blocco.
 */
class RequirePinUnlock
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user?->pin !== null && $request->session()->get('pin_unlocked') !== true) {
            return redirect()->route('unlock');
        }

        return $next($request);
    }
}
