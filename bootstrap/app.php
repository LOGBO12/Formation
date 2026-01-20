<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'check.profile' => \App\Http\Middleware\CheckProfileCompleted::class,
        ]);
        
        // CRITIQUE : Empêcher Laravel de chercher la route "login"
        $middleware->redirectGuestsTo(function ($request) {
            // Pour les requêtes API, retourner 401 au lieu de rediriger
            if ($request->is('api/*') || $request->expectsJson()) {
                abort(401, 'Non authentifié');
            }
            return route('login');
        });
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Gérer les erreurs d'authentification pour l'API
        $exceptions->render(function (AuthenticationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non authentifié. Token invalide ou expiré.',
                ], 401);
            }
        });
    })->create();