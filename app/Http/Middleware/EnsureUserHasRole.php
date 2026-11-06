<?php

namespace App\Http\Middleware;

use Illuminate\Support\Facades\Auth;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if ($request->user() && ! $request->user()->activo) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'Tu cuenta está desactivada. Contacta a un administrador.']);
        }

        if (! $request->user() || ! in_array($request->user()->rol, $roles, true)) {
            abort(response()->view('errors.web-access-denied', [], 403));
        }

        return $next($request);
    }
}
