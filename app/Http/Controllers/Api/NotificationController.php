<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Liste des notifications de l'utilisateur
     */
    public function index(Request $request)
    {
        try {
            $notifications = Notification::where('user_id', $request->user()->id)
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            return response()->json([
                'success' => true,
                'notifications' => $notifications,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des notifications',
            ], 500);
        }
    }

    /**
     * Compter les notifications non lues
     */
    public function compterNonLues(Request $request)
    {
        try {
            $count = $this->notificationService->compterNonLues($request->user()->id);

            return response()->json([
                'success' => true,
                'count' => $count,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du comptage',
            ], 500);
        }
    }

    /**
     * Marquer une notification comme lue
     */
    public function marquerCommeLue(Request $request, Notification $notification)
    {
        try {
            // Vérifier que la notification appartient à l'utilisateur
            if ($notification->user_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès non autorisé',
                ], 403);
            }

            $notification->marquerCommeLu();

            return response()->json([
                'success' => true,
                'message' => 'Notification marquée comme lue',
                'notification' => $notification,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour',
            ], 500);
        }
    }

    /**
     * Marquer toutes les notifications comme lues
     */
    public function marquerToutCommeLu(Request $request)
    {
        try {
            $count = $this->notificationService->marquerToutCommeLu($request->user()->id);

            return response()->json([
                'success' => true,
                'message' => "{$count} notifications marquées comme lues",
                'count' => $count,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour',
            ], 500);
        }
    }

    /**
     * Supprimer une notification
     */
    public function supprimer(Request $request, Notification $notification)
    {
        try {
            // Vérifier que la notification appartient à l'utilisateur
            if ($notification->user_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès non autorisé',
                ], 403);
            }

            $notification->delete();

            return response()->json([
                'success' => true,
                'message' => 'Notification supprimée',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression',
            ], 500);
        }
    }

    /**
     * Supprimer toutes les notifications lues
     */
    public function supprimerLues(Request $request)
    {
        try {
            $count = Notification::where('user_id', $request->user()->id)
                ->where('lu', true)
                ->delete();

            return response()->json([
                'success' => true,
                'message' => "{$count} notifications supprimées",
                'count' => $count,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression',
            ], 500);
        }
    }

    /**
     * Récupérer les notifications récentes (5 dernières)
     */
    public function recentes(Request $request)
    {
        try {
            $notifications = Notification::where('user_id', $request->user()->id)
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();

            return response()->json([
                'success' => true,
                'notifications' => $notifications,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération',
            ], 500);
        }
    }
}