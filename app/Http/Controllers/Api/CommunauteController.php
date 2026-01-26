<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Communaute;
use App\Models\MessageCommunaute;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CommunauteController extends Controller
{
    /**
     * Afficher une communauté avec toutes les infos
     */
    public function show(Request $request, Communaute $communaute)
    {
        try {
            \Log::info('🔵 Show communauté', ['id' => $communaute->id, 'user' => $request->user()->id]);

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

            return response()->json([
                'success' => true,
                'communaute' => $communauteData,
            ]);

        } catch (\Exception $e) {
            \Log::error('❌ Erreur show communauté:', [
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

            $messages = MessageCommunaute::where('communaute_id', $communaute->id)
                ->with([
                    'user:id,name,email',
                    'parent.user:id,name', // Message parent pour les réponses
                    'reactions.user:id,name',
                    'replies.user:id,name'
                ])
                ->whereNull('parent_message_id')
                ->whereNull('deleted_at')
                ->orderBy('is_pinned', 'desc')
                ->orderBy('is_announcement', 'desc')
                ->orderBy('created_at', 'asc')
                ->paginate(100);

            return response()->json([
                'success' => true,
                'messages' => $messages,
            ]);

        } catch (\Exception $e) {
            \Log::error('❌ Erreur messages:', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des messages',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Envoyer un message (texte, audio, fichier, vidéo, image)
     */
    public function envoyerMessage(Request $request, Communaute $communaute)
    {
        try {
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

            if ($membre->is_muted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez pas envoyer de messages (vous êtes muté)',
                ], 403);
            }

            // ✅ VALIDATION CORRIGÉE - Le message peut être vide si des fichiers sont présents
            $rules = [
                'message' => 'nullable|string|max:5000',  // ✅ nullable au lieu de required_without
                'type' => 'required|in:text,image,video,audio,pdf,file',
                'parent_message_id' => 'nullable|exists:messages_communaute,id',
                'files.*' => 'nullable|file|max:20480', // 20MB max
            ];

            // ✅ Validation personnalisée : au moins un message OU des fichiers
            $request->validate($rules);

            if (empty($request->message) && !$request->hasFile('files')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Veuillez fournir un message ou un fichier',
                ], 422);
            }

            $attachments = [];
            $attachmentsMeta = [];

            // Upload des fichiers
            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $file) {
                    $path = $file->store('communautes/' . $communaute->id, 'public');
                    $attachments[] = $path;
                    $attachmentsMeta[] = [
                        'name' => $file->getClientOriginalName(),
                        'size' => $file->getSize(),
                        'mime' => $file->getMimeType(),
                    ];
                }
            }

            // ✅ Créer le message avec un texte vide si nécessaire
            $message = MessageCommunaute::create([
                'communaute_id' => $communaute->id,
                'user_id' => $request->user()->id,
                'parent_message_id' => $request->parent_message_id,
                'message' => $request->message ?? '',  // ✅ String vide par défaut
                'type' => $request->type,
                'attachments' => $attachments,
                'attachments_meta' => $attachmentsMeta,
            ]);

            // Créer les mentions si présentes
            if (!empty($request->message)) {
                $message->createMentions();
            }

            // Charger les relations
            $message->load('user:id,name,email', 'parent.user:id,name', 'reactions');

            return response()->json([
                'success' => true,
                'message' => $message,
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('❌ Validation error:', [
                'errors' => $e->errors(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            \Log::error('❌ Erreur envoi message:', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'envoi',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Modifier un message
     */
    public function updateMessage(Request $request, MessageCommunaute $message)
    {
        try {
            // Seul l'auteur peut modifier son message
            if ($message->user_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorisé',
                ], 403);
            }

            // On ne peut modifier que le texte
            $request->validate([
                'message' => 'required|string|max:5000',
            ]);

            $message->update([
                'message' => $request->message,
                'is_edited' => true,
                'edited_at' => now(),
            ]);

            $message->load('user:id,name,email');

            return response()->json([
                'success' => true,
                'message' => $message,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la modification',
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

            $estAuteur = $message->user_id === $request->user()->id;
            
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

            // Supprimer les fichiers attachés
            if ($message->attachments) {
                foreach ($message->attachments as $path) {
                    Storage::disk('public')->delete($path);
                }
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
     * Épingler/Désépingler un message
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
     * Désépingler un message
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
                    'message' => 'Seuls les admins peuvent désépingler',
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
     * Ajouter/Retirer une réaction (emoji)
     */
    public function toggleReaction(Request $request, MessageCommunaute $message)
    {
        try {
            $request->validate([
                'reaction' => 'required|string|in:like,love,laugh,wow,sad,party,fire,clap',
            ]);

            $userId = $request->user()->id;
            $reaction = $request->reaction;

            // Vérifier si la réaction existe déjà
            $existing = $message->reactions()
                ->where('user_id', $userId)
                ->where('reaction', $reaction)
                ->first();

            if ($existing) {
                // Retirer la réaction
                $existing->delete();
                $action = 'removed';
            } else {
                // Ajouter la réaction
                $message->reactions()->create([
                    'user_id' => $userId,
                    'reaction' => $reaction,
                ]);
                $action = 'added';
            }

            // Retourner les réactions groupées
            $groupedReactions = $message->getGroupedReactions();

            return response()->json([
                'success' => true,
                'action' => $action,
                'reactions' => $groupedReactions,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la réaction',
            ], 500);
        }
    }

    /**
     * Obtenir les réponses d'un message (thread)
     */
    public function getReplies(Request $request, MessageCommunaute $message)
    {
        try {
            $replies = $message->replies()
                ->with('user:id,name,email', 'reactions.user:id,name')
                ->get();

            return response()->json([
                'success' => true,
                'replies' => $replies,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur',
            ], 500);
        }
    }

    /**
     * Marquer un message comme vu
     */
    public function markAsViewed(Request $request, MessageCommunaute $message)
    {
        try {
            $message->markAsViewedBy($request->user()->id);

            return response()->json([
                'success' => true,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur',
            ], 500);
        }
    }

    /**
     * Obtenir les mentions non lues
     */
    public function getUnreadMentions(Request $request)
    {
        try {
            $mentions = DB::table('message_mentions')
                ->join('messages_communaute', 'message_mentions.message_id', '=', 'messages_communaute.id')
                ->join('communautes', 'messages_communaute.communaute_id', '=', 'communautes.id')
                ->join('users', 'messages_communaute.user_id', '=', 'users.id')
                ->where('message_mentions.mentioned_user_id', $request->user()->id)
                ->where('message_mentions.is_read', false)
                ->whereNull('messages_communaute.deleted_at')
                ->select(
                    'message_mentions.*',
                    'messages_communaute.message',
                    'messages_communaute.created_at as message_created_at',
                    'communautes.nom as communaute_nom',
                    'users.name as author_name'
                )
                ->get();

            return response()->json([
                'success' => true,
                'mentions' => $mentions,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur',
            ], 500);
        }
    }

    /**
     * Marquer une mention comme lue
     */
    public function markMentionAsRead(Request $request, $mentionId)
    {
        try {
            DB::table('message_mentions')
                ->where('id', $mentionId)
                ->where('mentioned_user_id', $request->user()->id)
                ->update(['is_read' => true]);

            return response()->json([
                'success' => true,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur',
            ], 500);
        }
    }

    /**
     * Rechercher dans les messages
     */
    public function searchMessages(Request $request, Communaute $communaute)
    {
        try {
            $query = $request->input('q');

            if (!$query) {
                return response()->json([
                    'success' => false,
                    'message' => 'Query required',
                ], 400);
            }

            $messages = MessageCommunaute::where('communaute_id', $communaute->id)
                ->where('message', 'like', '%' . $query . '%')
                ->whereNull('deleted_at')
                ->with('user:id,name')
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get();

            return response()->json([
                'success' => true,
                'messages' => $messages,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de recherche',
            ], 500);
        }
    }

    /**
     * Statistiques de la communauté
     */
    public function getStats(Request $request, Communaute $communaute)
    {
        try {
            $stats = [
                'total_messages' => $communaute->messages()->count(),
                'total_members' => $communaute->totalMembres(),
                'messages_today' => $communaute->messages()
                    ->whereDate('created_at', today())
                    ->count(),
                'most_active_user' => DB::table('messages_communaute')
                    ->select('user_id', DB::raw('count(*) as count'))
                    ->where('communaute_id', $communaute->id)
                    ->whereNull('deleted_at')
                    ->groupBy('user_id')
                    ->orderBy('count', 'desc')
                    ->first(),
            ];

            return response()->json([
                'success' => true,
                'stats' => $stats,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur',
            ], 500);
        }
    }

    /**
     * Liste des membres
     */
    public function membres(Request $request, Communaute $communaute)
    {
        try {
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
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des membres',
            ], 500);
        }
    }

    /**
     * Muter un membre
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
                    'message' => 'Seuls les admins peuvent muter',
                ], 403);
            }

            $targetMembre = DB::table('communaute_membres')
                ->where('communaute_id', $communaute->id)
                ->where('user_id', $userId)
                ->first();

            if ($targetMembre && $targetMembre->role === 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de muter un admin',
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
     * Démuter un membre
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
                    'message' => 'Seuls les admins peuvent démuter',
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

    /**
     * Envoyer une annonce
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
}