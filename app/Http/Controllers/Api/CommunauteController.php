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
     * Afficher une communauté avec toutes les infos
     */
    public function show(Request $request, Communaute $communaute)
    {
        try {
            \Log::info('🔵 Show communauté', ['id' => $communaute->id, 'user' => $request->user()->id]);

            // Vérifier que l'utilisateur est membre
            $membre = DB::table('communaute_membres')
                ->where('communaute_id', $communaute->id)
                ->where('user_id', $request->user()->id)
                ->first();

            if (!$membre) {
                \Log::warning('⚠️ User not member', ['communaute' => $communaute->id, 'user' => $request->user()->id]);
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'êtes pas membre de cette communauté',
                ], 403);
            }

            // Charger les infos de la communauté
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
                'mon_role' => $membre->role ?? 'membre',
                'is_muted' => (bool)($membre->is_muted ?? false),
            ];

            \Log::info('✅ Communauté loaded', $communauteData);

            return response()->json([
                'success' => true,
                'communaute' => $communauteData,
            ]);

        } catch (\Exception $e) {
            \Log::error('❌ Erreur show communauté:', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
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
            \Log::info('🔵 Fetching messages', ['communaute' => $communaute->id]);

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

            // Récupérer les messages (les plus récents en premier)
            $messages = MessageCommunaute::where('communaute_id', $communaute->id)
                ->with('user:id,name,email')
                ->whereNull('parent_message_id')
                ->whereNull('deleted_at')
                ->orderBy('is_pinned', 'desc')
                ->orderBy('is_announcement', 'desc')
                ->orderBy('created_at', 'asc') // Ordre chronologique pour WhatsApp style
                ->paginate(100);

            \Log::info('✅ Messages loaded', ['count' => $messages->count()]);

            return response()->json([
                'success' => true,
                'messages' => $messages,
            ]);

        } catch (\Exception $e) {
            \Log::error('❌ Erreur messages:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des messages',
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
            \Log::info('🔵 Sending message', [
                'communaute' => $communaute->id,
                'user' => $request->user()->id,
                'message_length' => strlen($request->message ?? '')
            ]);

            // Vérifier membre
            $membre = DB::table('communaute_membres')
                ->where('communaute_id', $communaute->id)
                ->where('user_id', $request->user()->id)
                ->first();

            if (!$membre) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'êtes pas membre de cette communauté',
                ], 403);
            }

            // Vérifier si muté
            if ($membre->is_muted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez pas envoyer de messages (vous êtes muté)',
                ], 403);
            }

            $request->validate([
                'message' => 'required|string|max:5000',
            ]);

            // Créer le message
            $message = MessageCommunaute::create([
                'communaute_id' => $communaute->id,
                'user_id' => $request->user()->id,
                'message' => $request->message,
                'type' => 'text',
            ]);

            // Charger l'utilisateur pour le retour
            $message->load('user:id,name,email');

            \Log::info('✅ Message created', ['id' => $message->id]);

            return response()->json([
                'success' => true,
                'message' => $message,
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation échouée',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('❌ Erreur envoi message:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'envoi',
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

            // Récupérer les membres
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
                ->orderBy('communaute_membres.role', 'desc')
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
            \Log::error('❌ Erreur membres:', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des membres',
                'error' => config('app.debug') ? $e->getMessage() : null
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
                    'message' => 'Seuls les admins peuvent muter des membres',
                ], 403);
            }

            // Ne pas permettre de muter un admin
            $targetMembre = DB::table('communaute_membres')
                ->where('communaute_id', $communaute->id)
                ->where('user_id', $userId)
                ->first();

            if ($targetMembre && $targetMembre->role === 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de muter un administrateur',
                ], 403);
            }

            DB::table('communaute_membres')
                ->where('communaute_id', $communaute->id)
                ->where('user_id', $userId)
                ->update(['is_muted' => true]);

            return response()->json([
                'success' => true,
                'message' => 'Membre muté avec succès',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'action',
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
                    'message' => 'Seuls les admins peuvent démuter des membres',
                ], 403);
            }

            DB::table('communaute_membres')
                ->where('communaute_id', $communaute->id)
                ->where('user_id', $userId)
                ->update(['is_muted' => false]);

            return response()->json([
                'success' => true,
                'message' => 'Membre démuté avec succès',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'action',
            ], 500);
        }
    }

    /**
     * Supprimer un message (Auteur ou Admin)
     */
    public function supprimerMessage(Request $request, MessageCommunaute $message)
    {
        try {
            $communaute = $message->communaute;

            // Vérifier si c'est l'auteur
            $estAuteur = $message->user_id === $request->user()->id;
            
            // Vérifier si c'est un admin
            $estAdmin = DB::table('communaute_membres')
                ->where('communaute_id', $communaute->id)
                ->where('user_id', $request->user()->id)
                ->where('role', 'admin')
                ->exists();

            if (!$estAuteur && !$estAdmin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorisé à supprimer ce message',
                ], 403);
            }

            $message->delete();

            return response()->json([
                'success' => true,
                'message' => 'Message supprimé avec succès',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression',
            ], 500);
        }
    }

    /**
     * Envoyer une annonce (Admin seulement)
     */
    public function envoyerAnnonce(Request $request, Communaute $communaute)
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
                'type' => 'text',
            ]);

            $message->load('user:id,name,email');

            return response()->json([
                'success' => true,
                'message' => $message,
            ], 201);

        } catch (\Exception $e) {
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
                    'message' => 'Seuls les admins peuvent épingler des messages',
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
                    'message' => 'Seuls les admins peuvent désépingler des messages',
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
}