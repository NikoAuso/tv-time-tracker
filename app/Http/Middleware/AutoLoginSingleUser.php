<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * App personale a utente singolo: autentica automaticamente l'unico utente
 * locale, così non serve una schermata di login. Il PIN gestisce la privacy.
 */
class AutoLoginSingleUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check() && ($user = User::query()->orderBy('id')->first()) !== null) {
            Auth::login($user);
        }

        return $next($request);
    }
}
