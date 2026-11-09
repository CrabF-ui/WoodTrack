<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiUserIsActiveChofer
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user->activo) {
            return response()->json(['message' => 'Tu cuenta está desactivada. Contacta a un administrador.'], 403);
        }

        if ($user->rol !== User::ROL_CHOFER) {
            return response()->json(['message' => 'Esta cuenta no tiene acceso a la app.'], 403);
        }

        return $next($request);
    }
}
