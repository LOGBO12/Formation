<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ProfileController extends Controller
{
    /**
     * Mettre à jour le profil
     */
    public function updateProfile(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'bio' => 'nullable|string|max:1000',
                'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);

            $user = $request->user();

            // Mettre à jour le nom
            $user->update([
                'name' => $request->name,
            ]);

            // Gérer la photo
            if ($request->hasFile('photo')) {
                // Supprimer l'ancienne photo si elle existe
                if ($user->profile && $user->profile->photo) {
                    Storage::disk('public')->delete($user->profile->photo);
                }

                // Enregistrer la nouvelle photo
                $photoPath = $request->file('photo')->store('profiles', 'public');
                
                $user->profile()->updateOrCreate(
                    ['user_id' => $user->id],
                    ['photo' => $photoPath]
                );
            }

            // Mettre à jour la bio
            if ($request->has('bio')) {
                $user->profile()->updateOrCreate(
                    ['user_id' => $user->id],
                    ['bio' => $request->bio]
                );
            }

            // Recharger l'utilisateur avec ses relations
            $user->load(['profile', 'domaines']);

            return response()->json([
                'success' => true,
                'message' => 'Profil mis à jour avec succès',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'profile' => $user->profile,
                    'domaines' => $user->domaines,
                ],
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Données invalides',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('Erreur updateProfile: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour',
            ], 500);
        }
    }

    /**
     * Changer le mot de passe
     */
    public function changePassword(Request $request)
    {
        try {
            $request->validate([
                'current_password' => 'required',
                'new_password' => 'required|min:8|confirmed',
            ]);

            $user = $request->user();

            // Vérifier le mot de passe actuel
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le mot de passe actuel est incorrect',
                ], 422);
            }

            // Mettre à jour le mot de passe
            $user->update([
                'password' => Hash::make($request->new_password),
            ]);

            // Supprimer tous les tokens existants sauf le token actuel
            $currentToken = $user->currentAccessToken();
            $user->tokens()->where('id', '!=', $currentToken->id)->delete();

            Log::info('Mot de passe changé', ['user_id' => $user->id]);

            return response()->json([
                'success' => true,
                'message' => 'Mot de passe mis à jour avec succès',
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Données invalides',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('Erreur changePassword: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du changement de mot de passe',
            ], 500);
        }
    }

    /**
     * Mettre à jour les préférences de notifications
     */
    public function updateNotifications(Request $request)
    {
        try {
            $request->validate([
                'email_formations' => 'required|boolean',
                'email_communaute' => 'required|boolean',
                'email_marketing' => 'required|boolean',
            ]);

            $user = $request->user();

            $user->profile()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'notifications_preferences' => json_encode([
                        'email_formations' => $request->email_formations,
                        'email_communaute' => $request->email_communaute,
                        'email_marketing' => $request->email_marketing,
                    ]),
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Préférences enregistrées',
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur updateNotifications: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour',
            ], 500);
        }
    }

    /**
     * Télécharger les données de l'utilisateur
     */
    public function downloadData(Request $request)
    {
        try {
            $user = $request->user();
            $user->load(['profile', 'domaines', 'inscriptions', 'paiements']);

            $data = [
                'user' => $user->toArray(),
                'exported_at' => now()->toIso8601String(),
            ];

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur downloadData: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'export',
            ], 500);
        }
    }

    /**
     * Supprimer le compte
     */
    public function deleteAccount(Request $request)
    {
        try {
            $user = $request->user();

            // Supprimer la photo de profil
            if ($user->profile && $user->profile->photo) {
                Storage::disk('public')->delete($user->profile->photo);
            }

            // Supprimer le profil
            if ($user->profile) {
                $user->profile->delete();
            }

            // Supprimer tous les tokens
            $user->tokens()->delete();

            // Supprimer l'utilisateur
            $user->delete();

            Log::info('Compte supprimé', ['user_id' => $user->id]);

            return response()->json([
                'success' => true,
                'message' => 'Compte supprimé avec succès',
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur deleteAccount: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression',
            ], 500);
        }
    }
}