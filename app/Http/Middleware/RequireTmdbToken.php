<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * L'app richiede un token TMDB per-utente: senza, ogni pagina reindirizza alla
 * schermata di import dove lo si inserisce. Con il token, lo inietta nella
 * config così i servizi TMDB lo usano per questa richiesta (nessun fallback
 * a un token globale imbarcato).
 */
class RequireTmdbToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user?->tmdb_token === null) {
            return redirect()->route('import.edit');
        }

        config(['services.tmdb.token' => $user->tmdb_token]);

        return $next($request);
    }
}
