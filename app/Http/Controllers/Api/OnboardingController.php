<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserProfile;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    /**
     * Étape 1 : Sélection du rôle
     */
    public function selectRole(Request $request)
    {
        $request->validate([
            'role' => 'required|in:formateur,apprenant',
        ]);

        $user = $request->user();

        $user->update([
            'role' => $request->role,
            'role_selected_at' => now(),
            'onboarding_step' => 'profile',
        ]);

        // Assigner le rôle avec Spatie
        $user->syncRoles([$request->role]);

        return response()->json([
            'success' => true,
            'message' => 'Rôle sélectionné avec succès',
            'user' => [
                'role' => $user->role,
                'onboarding_step' => $user->onboarding_step,
            ],
        ]);
    }

    /**
     * Étape 2 : Compléter le profil
     */
    public function completeProfile(Request $request)
    {
        $user = $request->user();

        $rules = [
            'domaines' => 'required|array|min:1',
            'domaines.*' => 'exists:domaines,id',
            'experience_level' => 'required|in:debutant,intermediaire,avance,expert',
        ];

        $request->validate($rules);

        // Créer ou mettre à jour le profil
        UserProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'experience_level' => $request->experience_level,
            ]
        );

        // Attacher les domaines
        $user->domaines()->sync($request->domaines);

        // Mettre à jour l'étape d'onboarding
        $user->update([
            'onboarding_step' => 'privacy_policy',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Profil complété avec succès',
            'user' => [
                'onboarding_step' => $user->onboarding_step,
            ],
        ]);
    }

    /**
     * Étape 3 : Accepter la politique de confidentialité
     */
    public function acceptPrivacyPolicy(Request $request)
    {
        $request->validate([
            'accepted' => 'required|accepted',
        ]);

        $user = $request->user();

        $user->update([
            'profile_completed' => true,
            'onboarding_step' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Onboarding terminé ! Bienvenue sur la plateforme.',
            'user' => [
                'profile_completed' => $user->profile_completed,
                'role' => $user->role,
            ],
        ]);
    }

    /**
     * Passer l'étape du profil (optionnel)
     */
    public function skipProfile(Request $request)
    {
        $user = $request->user();

        $user->update([
            'onboarding_step' => 'privacy_policy',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Étape ignorée',
            'user' => [
                'onboarding_step' => $user->onboarding_step,
            ],
        ]);
    }
}