<?php
// app/Http/Middleware/AdminMiddleware.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!auth()->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Non authentifié'
            ], 401);
        }

        $user = auth()->user();

        // Vérifier si l'utilisateur a le rôle admin
        // Option 1: Colonne is_admin dans la table users
        if (!$user->is_admin) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé - Droits administrateur requis'
            ], 403);
        }

        // Option 2: Si vous utilisez Spatie Permission (plus avancé)
        // if (!$user->hasRole('admin')) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Accès non autorisé - Droits administrateur requis'
        //     ], 403);
        // }

        return $next($request);
    }
}

// Enregistrer le middleware dans app/Http/Kernel.php
// protected $routeMiddleware = [
//     // ... autres middlewares
//     'admin' => \App\Http\Middleware\AdminMiddleware::class,
// ];