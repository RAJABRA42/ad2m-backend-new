<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Non authentifié'], 401);
        }

        // accepte "admin" OU "administrateur"
        if (!$user->hasRole(['admin', 'administrateur'])) {
            return response()->json(['message' => 'Accès refusé (admin requis)'], 403);
        }

        return $next($request);
    }
}
