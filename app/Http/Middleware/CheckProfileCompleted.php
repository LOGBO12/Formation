<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class CheckProfileCompleted
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Log pour débugger
        Log::info('CheckProfile Middleware', [
            'user_id' => $user?->id,
            'role' => $user?->role,
            'profile_completed' => $user?->profile_completed,
        ]);

        // Si pas d'utilisateur, laisser Sanctum gérer
        if (!$user) {
            Log::error('CheckProfile: Pas d\'utilisateur authentifié');
            return response()->json([
                'success' => false,
                'message' => 'Non authentifié',
            ], 401);
        }

        // Super Admin passe toujours
        if ($user->isSuperAdmin()) {
            Log::info('CheckProfile: Super Admin - OK');
            return $next($request);
        }

        // Si profil complété, laisser passer
        if ($user->profile_completed) {
            Log::info('CheckProfile: Profil complété - OK');
            return $next($request);
        }

        // Si profil non complété
        Log::warning('CheckProfile: Profil non complété', ['user_id' => $user->id]);
        return response()->json([
            'success' => false,
            'message' => 'Veuillez compléter votre profil',
            'needs_onboarding' => true,
            'onboarding_step' => $user->onboarding_step ?? 'role',
        ], 403);
    }
}