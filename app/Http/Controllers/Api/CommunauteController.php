<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Communaute;
use App\Models\MessageCommunaute;
use Illuminate\Http\Request;

class CommunauteController extends Controller
{
    /**
     * Afficher une communauté
     */
    public function show(Communaute $communaute)
    {
        $communaute->load(['formation', 'membres']);

        return response()->json([
            'success' => true,
            'communaute' => $communaute,
        ]);
    }

    /**
     * Messages d'une communauté
     */
    public function messages(Request $request, Communaute $communaute)
    {
        // Vérifier que l'utilisateur est membre
        if (!$communaute->membres()->where('user_id', $request->user()->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas membre de cette communauté',
            ], 403);
        }

        $messages = $communaute->messages()
            ->with('user')
            ->orderBy('is_pinned', 'desc')
            ->orderBy('is_announcement', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return response()->json([
            'success' => true,
            'messages' => $messages,
        ]);
    }

    /**
     * Envoyer un message
     */
    public function envoyerMessage(Request $request, Communaute $communaute)
    {
        // Vérifier que l'utilisateur est membre
        if (!$communaute->membres()->where('user_id', $request->user()->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas membre de cette communauté',
            ], 403);
        }

        // Vérifier si l'utilisateur est muté
        if ($communaute->estMute($request->user()->id)) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez pas envoyer de messages',
            ], 403);
        }

        $request->validate([
            'message' => 'required|string|max:5000',
        ]);

        $message = $communaute->messages()->create([
            'user_id' => $request->user()->id,
            'message' => $request->message,
        ]);

        return response()->json([
            'success' => true,
            'message' => $message->load('user'),
        ], 201);
    }

    /**
     * Envoyer une annonce (Admin seulement)
     */
    public function envoyerAnnonce(Request $request, Communaute $communaute)
    {
        // Vérifier que l'utilisateur est admin
        if (!$communaute->estAdmin($request->user()->id)) {
            return response()->json([
                'success' => false,
                'message' => 'Seuls les admins peuvent envoyer des annonces',
            ], 403);
        }

        $request->validate([
            'message' => 'required|string|max:5000',
        ]);

        $message = $communaute->messages()->create([
            'user_id' => $request->user()->id,
            'message' => $request->message,
            'is_announcement' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => $message->load('user'),
        ], 201);
    }

    /**
     * Épingler un message (Admin seulement)
     */
    public function epinglerMessage(Request $request, MessageCommunaute $message)
    {
        $communaute = $message->communaute;

        if (!$communaute->estAdmin($request->user()->id)) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé',
            ], 403);
        }

        $message->epingler();

        return response()->json([
            'success' => true,
            'message' => 'Message épinglé',
        ]);
    }

    /**
     * Désépingler un message (Admin seulement)
     */
    public function desepinglerMessage(Request $request, MessageCommunaute $message)
    {
        $communaute = $message->communaute;

        if (!$communaute->estAdmin($request->user()->id)) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé',
            ], 403);
        }

        $message->desepingler();

        return response()->json([
            'success' => true,
            'message' => 'Message désépinglé',
        ]);
    }

    /**
     * Supprimer un message
     */
    public function supprimerMessage(Request $request, MessageCommunaute $message)
    {
        $communaute = $message->communaute;

        // L'auteur ou un admin peut supprimer
        if ($message->user_id !== $request->user()->id && !$communaute->estAdmin($request->user()->id)) {
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
    }

    /**
     * Muter un membre (Admin seulement)
     */
    public function muterMembre(Request $request, Communaute $communaute, $userId)
    {
        if (!$communaute->estAdmin($request->user()->id)) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé',
            ], 403);
        }

        $communaute->muterMembre($userId);

        return response()->json([
            'success' => true,
            'message' => 'Membre muté',
        ]);
    }

    /**
     * Démuter un membre (Admin seulement)
     */
    public function demuterMembre(Request $request, Communaute $communaute, $userId)
    {
        if (!$communaute->estAdmin($request->user()->id)) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé',
            ], 403);
        }

        $communaute->demuterMembre($userId);

        return response()->json([
            'success' => true,
            'message' => 'Membre démuté',
        ]);
    }

    /**
     * Liste des membres
     */
    public function membres(Request $request, Communaute $communaute)
    {
        // Vérifier que l'utilisateur est membre
        if (!$communaute->membres()->where('user_id', $request->user()->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé',
            ], 403);
        }

        $membres = $communaute->membres()->get();

        return response()->json([
            'success' => true,
            'membres' => $membres,
        ]);
    }
}