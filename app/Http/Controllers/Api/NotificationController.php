<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Liste des notifications de l'utilisateur (avec pagination)
     */
    public function index(Request $request)
    {
        try {
            $notifications = Notification::where('user_id', $request->user()->id)
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            // ✅ CORRECTION: Ajouter temps_ecoule manuellement à chaque notification
            $notifications->getCollection()->transform(function ($notification) {
                $notification->temps_ecoule = $notification->getTempsEcouleAttribute();
                return $notification;
            });

            return response()->json([
                'success' => true,
                'notifications' => $notifications,
            ], 200);

        } catch (\Exception $e) {
            Log::error('❌ Erreur index notifications:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des notifications',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * ✅ CORRECTION: Récupérer les notifications récentes (5 dernières)
     * Gestion robuste des erreurs
     */
    public function recentes(Request $request)
    {
        try {
            Log::info('📥 Récupération notifications récentes', [
                'user_id' => $request->user()->id,
            ]);

            $notifications = Notification::where('user_id', $request->user()->id)
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();

            // ✅ Ajouter temps_ecoule à chaque notification
            $notifications->transform(function ($notification) {
                $notification->temps_ecoule = $notification->getTempsEcouleAttribute();
                return $notification;
            });

            Log::info('✅ Notifications récupérées', [
                'count' => $notifications->count(),
            ]);

            return response()->json([
                'success' => true,
                'notifications' => $notifications,
            ], 200);

        } catch (\Exception $e) {
            Log::error('❌ Erreur recentes notifications:', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * ✅ CORRECTION: Compter les notifications non lues
     */
    public function compterNonLues(Request $request)
    {
        try {
            Log::info('🔢 Comptage notifications non lues', [
                'user_id' => $request->user()->id,
            ]);

            $count = Notification::where('user_id', $request->user()->id)
                ->where('lu', false)
                ->count();

            Log::info('✅ Comptage réussi', ['count' => $count]);

            return response()->json([
                'success' => true,
                'count' => $count,
            ], 200);

        } catch (\Exception $e) {
            Log::error('❌ Erreur compterNonLues:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du comptage',
                'error' => config('app.debug') ? $e->getMessage() : null,
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
            $notification->temps_ecoule = $notification->getTempsEcouleAttribute();

            return response()->json([
                'success' => true,
                'message' => 'Notification marquée comme lue',
                'notification' => $notification,
            ], 200);

        } catch (\Exception $e) {
            Log::error('❌ Erreur marquerCommeLue:', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Marquer toutes les notifications comme lues
     */
    public function marquerToutCommeLu(Request $request)
    {
        try {
            $count = Notification::where('user_id', $request->user()->id)
                ->where('lu', false)
                ->update([
                    'lu' => true,
                    'lu_at' => now(),
                ]);

            return response()->json([
                'success' => true,
                'message' => "{$count} notification(s) marquée(s) comme lue(s)",
                'count' => $count,
            ], 200);

        } catch (\Exception $e) {
            Log::error('❌ Erreur marquerToutCommeLu:', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour',
                'error' => config('app.debug') ? $e->getMessage() : null,
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
            Log::error('❌ Erreur supprimer:', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression',
                'error' => config('app.debug') ? $e->getMessage() : null,
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
                'message' => "{$count} notification(s) supprimée(s)",
                'count' => $count,
            ], 200);

        } catch (\Exception $e) {
            Log::error('❌ Erreur supprimerLues:', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}