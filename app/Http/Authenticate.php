<?php
protected function redirectTo($request): ?string
{
    // Si c'est une requête API, renvoie 401 en JSON
    if ($request->expectsJson()) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    // (Optionnel) Si c'est une requête web (non-API), redirige vers la page de login
    return route('login');
}
