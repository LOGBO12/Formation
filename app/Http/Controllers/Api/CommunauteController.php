<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Communaute;
use App\Models\MessageCommunaute;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommunauteController extends Controller
{
    /**
     * Afficher une communauté
     */
    public function show(Request $request, Communaute $communaute)
    {
        try {
            // Vérifier que l'utilisateur est membre
            $estMembre = DB::table('communaute_membres')
                ->where('communaute_id', $communaute->id)
                ->where('user_id', $request->user()->id)
                ->exists();

            if (!$estMembre) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'êtes pas membre de cette communauté',
                ], 403);
            }

            // Charger les données de manière optimisée
            $communauteData = [
                'id' => $communaute->id,
                'nom' => $communaute->nom,
                'description' => $communaute->description,
                'formation' => DB::table('formations')
                    ->leftJoin('domaines', 'formations.domaine_id', '=', 'domaines.id')
                    ->leftJoin('users', 'formations.formateur_id', '=', 'users.id')
                    ->where('formations.id', $communaute->formation_id)
                    ->select(
                        'formations.id',
                        'formations.titre',
                        'domaines.name as domaine_name',
                        'users.name as formateur_name'
                    )
                    ->first(),
                'total_membres' => DB::table('communaute_membres')
                    ->where('communaute_id', $communaute->id)
                    ->count(),
                'total_messages' => DB::table('messages_communaute')
                    ->where('communaute_id', $communaute->id)
                    ->whereNull('deleted_at')
                    ->count(),
            ];

            return response()->json([
                'success' => true,
                'communaute' => $communauteData,
            ]);

        } catch (\Exception $e) {
            \Log::error('Erreur show communauté:', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Messages d'une communauté (avec pagination)
     */
    public function messages(Request $request, Communaute $communaute)
    {
        try {
            // Vérifier que l'utilisateur est membre
            $estMembre = DB::table('communaute_membres')
                ->where('communaute_id', $communaute->id)
                ->where('user_id', $request->user()->id)
                ->exists();

            if (!$estMembre) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'êtes pas membre de cette communauté',
                ], 403);
            }

            // Récupérer les messages avec pagination
            $messages = MessageCommunaute::where('communaute_id', $communaute->id)
                ->with('user:id,name,email')
                ->whereNull('parent_message_id') // Seulement messages de premier niveau
                ->orderBy('is_pinned', 'desc')
                ->orderBy('is_announcement', 'desc')
                ->orderBy('created_at', 'desc')
                ->paginate(50);

            return response()->json([
                'success' => true,
                'messages' => $messages,
            ]);

        } catch (\Exception $e) {
            \Log::error('Erreur messages communauté:', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des messages',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Liste des membres
     */
    public function membres(Request $request, Communaute $communaute)
    {
        try {
            // Vérifier que l'utilisateur est membre
            $estMembre = DB::table('communaute_membres')
                ->where('communaute_id', $communaute->id)
                ->where('user_id', $request->user()->id)
                ->exists();

            if (!$estMembre) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'êtes pas membre de cette communauté',
                ], 403);
            }

            // Récupérer les membres avec leurs infos
            $membres = DB::table('users')
                ->join('communaute_membres', 'users.id', '=', 'communaute_membres.user_id')
                ->where('communaute_membres.communaute_id', $communaute->id)
                ->select(
                    'users.id',
                    'users.name',
                    'users.email',
                    'communaute_membres.role',
                    'communaute_membres.is_muted',
                    'communaute_membres.joined_at'
                )
                ->orderBy('communaute_membres.role', 'desc') // Admins en premier
                ->orderBy('communaute_membres.joined_at', 'asc')
                ->get()
                ->map(function ($membre) {
                    return [
                        'id' => $membre->id,
                        'name' => $membre->name,
                        'email' => $membre->email,
                        'pivot' => [
                            'role' => $membre->role,
                            'is_muted' => (bool)$membre->is_muted,
                            'joined_at' => $membre->joined_at,
                        ]
                    ];
                });

            return response()->json([
                'success' => true,
                'membres' => $membres,
            ]);

        } catch (\Exception $e) {
            \Log::error('Erreur membres communauté:', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des membres',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Envoyer un message
     */
    public function envoyerMessage(Request $request, Communaute $communaute)
    {
        try {
            // Vérifier membre
            $estMembre = DB::table('communaute_membres')
                ->where('communaute_id', $communaute->id)
                ->where('user_id', $request->user()->id)
                ->exists();

            if (!$estMembre) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'êtes pas membre de cette communauté',
                ], 403);
            }

            // Vérifier si muté
            $estMute = DB::table('communaute_membres')
                ->where('communaute_id', $communaute->id)
                ->where('user_id', $request->user()->id)
                ->where('is_muted', true)
                ->exists();

            if ($estMute) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez pas envoyer de messages',
                ], 403);
            }

            $request->validate([
                'message' => 'required|string|max:5000',
            ]);

            $message = MessageCommunaute::create([
                'communaute_id' => $communaute->id,
                'user_id' => $request->user()->id,
                'message' => $request->message,
            ]);

            return response()->json([
                'success' => true,
                'message' => $message->load('user:id,name,email'),
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Erreur envoi message:', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'envoi',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Envoyer une annonce (Admin seulement)
     */
    public function envoyerAnnonce(Request $request, Communaute $communaute)
    {
        try {
            // Vérifier si admin
            $estAdmin = DB::table('communaute_membres')
                ->where('communaute_id', $communaute->id)
                ->where('user_id', $request->user()->id)
                ->where('role', 'admin')
                ->exists();

            if (!$estAdmin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Seuls les admins peuvent envoyer des annonces',
                ], 403);
            }

            $request->validate([
                'message' => 'required|string|max:5000',
            ]);

            $message = MessageCommunaute::create([
                'communaute_id' => $communaute->id,
                'user_id' => $request->user()->id,
                'message' => $request->message,
                'is_announcement' => true,
            ]);

            return response()->json([
                'success' => true,
                'message' => $message->load('user:id,name,email'),
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Erreur envoi annonce:', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'envoi',
            ], 500);
        }
    }

    /**
     * Épingler un message (Admin seulement)
     */
    public function epinglerMessage(Request $request, MessageCommunaute $message)
    {
        try {
            $communaute = $message->communaute;

            $estAdmin = DB::table('communaute_membres')
                ->where('communaute_id', $communaute->id)
                ->where('user_id', $request->user()->id)
                ->where('role', 'admin')
                ->exists();

            if (!$estAdmin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorisé',
                ], 403);
            }

            $message->update(['is_pinned' => true]);

            return response()->json([
                'success' => true,
                'message' => 'Message épinglé',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur',
            ], 500);
        }
    }

    /**
     * Désépingler un message (Admin seulement)
     */
    public function desepinglerMessage(Request $request, MessageCommunaute $message)
    {
        try {
            $communaute = $message->communaute;

            $estAdmin = DB::table('communaute_membres')
                ->where('communaute_id', $communaute->id)
                ->where('user_id', $request->user()->id)
                ->where('role', 'admin')
                ->exists();

            if (!$estAdmin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorisé',
                ], 403);
            }

            $message->update(['is_pinned' => false]);

            return response()->json([
                'success' => true,
                'message' => 'Message désépinglé',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur',
            ], 500);
        }
    }

    /**
     * Supprimer un message
     */
    public function supprimerMessage(Request $request, MessageCommunaute $message)
    {
        try {
            $communaute = $message->communaute;

            // L'auteur ou un admin peut supprimer
            $estAuteur = $message->user_id === $request->user()->id;
            
            $estAdmin = DB::table('communaute_membres')
                ->where('communaute_id', $communaute->id)
                ->where('user_id', $request->user()->id)
                ->where('role', 'admin')
                ->exists();

            if (!$estAuteur && !$estAdmin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorisé',
                ], 403);
            }

            $message->delete();

            return response()->json([
                'success' => true,
                'message' => 'Message supprimé',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur',
            ], 500);
        }
    }

    /**
     * Muter un membre (Admin seulement)
     */
    public function muterMembre(Request $request, Communaute $communaute, $userId)
    {
        try {
            $estAdmin = DB::table('communaute_membres')
                ->where('communaute_id', $communaute->id)
                ->where('user_id', $request->user()->id)
                ->where('role', 'admin')
                ->exists();

            if (!$estAdmin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorisé',
                ], 403);
            }

            DB::table('communaute_membres')
                ->where('communaute_id', $communaute->id)
                ->where('user_id', $userId)
                ->update(['is_muted' => true]);

            return response()->json([
                'success' => true,
                'message' => 'Membre muté',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur',
            ], 500);
        }
    }

    /**
     * Démuter un membre (Admin seulement)
     */
    public function demuterMembre(Request $request, Communaute $communaute, $userId)
    {
        try {
            $estAdmin = DB::table('communaute_membres')
                ->where('communaute_id', $communaute->id)
                ->where('user_id', $request->user()->id)
                ->where('role', 'admin')
                ->exists();

            if (!$estAdmin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorisé',
                ], 403);
            }

            DB::table('communaute_membres')
                ->where('communaute_id', $communaute->id)
                ->where('user_id', $userId)
                ->update(['is_muted' => false]);

            return response()->json([
                'success' => true,
                'message' => 'Membre démuté',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur',
            ], 500);
        }
    }
}