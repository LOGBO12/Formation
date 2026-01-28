<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Communaute;
use App\Services\NotificationService; // 🆕
use Illuminate\Http\Request;

class MessageController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Envoyer un message dans une communauté
     */
    public function store(Request $request, Communaute $communaute)
    {
        $request->validate([
            'contenu' => 'required|string',
        ]);

        $message = Message::create([
            'communaute_id' => $communaute->id,
            'user_id' => $request->user()->id,
            'contenu' => $request->contenu,
        ]);

        // 🆕 Notifier tous les membres de la communauté (sauf l'auteur)
        $this->notificationService->notifierNouveauMessage($message, $communaute);

        return response()->json([
            'success' => true,
            'message' => 'Message envoyé avec succès',
            'data' => $message->load('user'),
        ], 201);
    }
}