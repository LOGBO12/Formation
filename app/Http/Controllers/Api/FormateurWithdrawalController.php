<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FormateurWithdrawal;
use App\Services\FormateurRevenueService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class FormateurWithdrawalController extends Controller
{
    protected $revenueService;
    protected $notificationService;

    public function __construct(
        FormateurRevenueService $revenueService,
        NotificationService $notificationService
    ) {
        $this->revenueService = $revenueService;
        $this->notificationService = $notificationService;
    }

    /**
     * Obtenir le solde et les statistiques
     */
    public function getBalance(Request $request)
    {
        try {
            $formateurId = $request->user()->id;
            
            $stats = $this->revenueService->getRevenueStats($formateurId);
            $history = $this->revenueService->getWithdrawalHistory($formateurId, 10);

            return response()->json([
                'success' => true,
                'stats' => $stats,
                'recent_withdrawals' => $history,
            ], 200);

        } catch (\Exception $e) {
            Log::error('❌ Erreur getBalance', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du solde',
            ], 500);
        }
    }

    /**
     * Créer une demande de retrait
     */
    public function requestWithdrawal(Request $request)
    {
        try {
            $request->validate([
                'montant_demande' => 'required|numeric|min:1000',
                'phone_number' => 'required|string|min:8',
                'phone_country' => 'required|in:bj,tg,ci,sn,ml,bf,ne',
            ]);

            $formateurId = $request->user()->id;
            $montantDemande = (float) $request->montant_demande;

            Log::info('💰 Demande de retrait reçue', [
                'formateur_id' => $formateurId,
                'montant' => $montantDemande,
            ]);

            // Vérifier si possible
            $check = $this->revenueService->canWithdraw($formateurId, $montantDemande);

            if (!$check['can_withdraw']) {
                return response()->json([
                    'success' => false,
                    'message' => "Solde insuffisant. Vous avez {$check['solde_disponible']} FCFA disponible.",
                    'solde_disponible' => $check['solde_disponible'],
                    'montant_manquant' => $check['montant_manquant'],
                ], 400);
            }

            // Créer la demande
            DB::beginTransaction();

            $withdrawal = FormateurWithdrawal::create([
                'formateur_id' => $formateurId,
                'montant_demande' => $montantDemande,
                'solde_disponible' => $check['solde_disponible'],
                'phone_number' => $request->phone_number,
                'phone_country' => $request->phone_country,
                'statut' => 'pending',
            ]);

            // Notifier l'admin
            $this->notifyAdminNewWithdrawal($withdrawal);

            // Notifier le formateur
            $this->notificationService->creer(
                $formateurId,
                'retrait_demande',
                'Demande de retrait créée',
                "Votre demande de retrait de {$montantDemande} FCFA a été envoyée à l'administrateur pour validation.",
                '/formateur/revenus',
                [
                    'withdrawal_id' => $withdrawal->id,
                    'montant' => $montantDemande,
                ]
            );

            DB::commit();

            Log::info('✅ Demande de retrait créée', [
                'withdrawal_id' => $withdrawal->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Demande de retrait envoyée avec succès',
                'withdrawal' => $withdrawal,
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Données invalides',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('❌ Erreur requestWithdrawal', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de la demande',
            ], 500);
        }
    }

    /**
     * Historique des retraits
     */
    public function history(Request $request)
    {
        try {
            $withdrawals = FormateurWithdrawal::where('formateur_id', $request->user()->id)
                ->with('processedBy')
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            return response()->json([
                'success' => true,
                'withdrawals' => $withdrawals,
            ], 200);

        } catch (\Exception $e) {
            Log::error('❌ Erreur history', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération',
            ], 500);
        }
    }

    /**
     * Annuler une demande (si encore pending)
     */
    public function cancel(Request $request, FormateurWithdrawal $withdrawal)
    {
        try {
            if ($withdrawal->formateur_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorisé',
                ], 403);
            }

            if (!$withdrawal->isPending()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cette demande ne peut plus être annulée',
                ], 400);
            }

            $withdrawal->update(['statut' => 'rejected', 'admin_notes' => 'Annulé par le formateur']);

            Log::info('🚫 Demande de retrait annulée', [
                'withdrawal_id' => $withdrawal->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Demande annulée',
            ], 200);

        } catch (\Exception $e) {
            Log::error('❌ Erreur cancel', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'annulation',
            ], 500);
        }
    }

    /**
     * Notifier les admins d'une nouvelle demande
     */
    protected function notifyAdminNewWithdrawal($withdrawal)
    {
        try {
            $admins = \App\Models\User::where('role', 'super_admin')->get();

            foreach ($admins as $admin) {
                $this->notificationService->creer(
                    $admin->id,
                    'nouvelle_demande_retrait',
                    '💰 Nouvelle demande de retrait',
                    "{$withdrawal->formateur->name} a demandé un retrait de {$withdrawal->montant_demande} FCFA. Merci de valider cette demande.",
                    '/admin/retraits',
                    [
                        'withdrawal_id' => $withdrawal->id,
                        'formateur_id' => $withdrawal->formateur_id,
                        'formateur_nom' => $withdrawal->formateur->name,
                        'montant' => $withdrawal->montant_demande,
                    ]
                );
            }

            Log::info('🔔 Admins notifiés de la nouvelle demande', [
                'withdrawal_id' => $withdrawal->id,
                'nombre_admins' => $admins->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Erreur notifyAdminNewWithdrawal', [
                'message' => $e->getMessage(),
            ]);
        }
    }
}