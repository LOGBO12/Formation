<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FormateurWithdrawal;
use App\Services\NotificationService;
use App\Services\FedaPayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class AdminWithdrawalController extends Controller
{
    protected $notificationService;
    protected $fedaPayService;

    public function __construct(
        NotificationService $notificationService,
        FedaPayService $fedaPayService
    ) {
        $this->notificationService = $notificationService;
        $this->fedaPayService = $fedaPayService;
    }

    /**
     * Liste des demandes de retrait
     */
    public function index(Request $request)
    {
        if (!$request->user()->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé',
            ], 403);
        }

        try {
            $status = $request->input('status', 'all');

            $query = FormateurWithdrawal::with(['formateur', 'processedBy'])
                ->orderBy('created_at', 'desc');

            if ($status !== 'all') {
                $query->where('statut', $status);
            }

            $withdrawals = $query->paginate(20);

            // Stats
            $stats = [
                'total' => FormateurWithdrawal::count(),
                'pending' => FormateurWithdrawal::where('statut', 'pending')->count(),
                'approved' => FormateurWithdrawal::where('statut', 'approved')->count(),
                'completed' => FormateurWithdrawal::where('statut', 'completed')->count(),
                'rejected' => FormateurWithdrawal::where('statut', 'rejected')->count(),
                'montant_pending' => FormateurWithdrawal::where('statut', 'pending')->sum('montant_demande'),
                'montant_completed' => FormateurWithdrawal::where('statut', 'completed')->sum('montant_demande'),
            ];

            return response()->json([
                'success' => true,
                'withdrawals' => $withdrawals,
                'stats' => $stats,
            ], 200);

        } catch (\Exception $e) {
            Log::error('❌ Erreur index withdrawals', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement',
            ], 500);
        }
    }

    /**
     * Approuver une demande de retrait
     */
    public function approve(Request $request, FormateurWithdrawal $withdrawal)
    {
        if (!$request->user()->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé',
            ], 403);
        }

        try {
            $request->validate([
                'admin_notes' => 'nullable|string|max:500',
            ]);

            if (!$withdrawal->isPending()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cette demande a déjà été traitée',
                ], 400);
            }

            DB::beginTransaction();

            // Approuver
            $withdrawal->approve($request->user()->id, $request->admin_notes);

            // Notifier le formateur
            $this->notificationService->creer(
                $withdrawal->formateur_id,
                'retrait_approuve',
                '✅ Retrait approuvé',
                "Votre demande de retrait de {$withdrawal->montant_demande} FCFA a été approuvée. Le paiement sera effectué sous 24-48h.",
                '/formateur/revenus',
                [
                    'withdrawal_id' => $withdrawal->id,
                    'montant' => $withdrawal->montant_demande,
                ]
            );

            // TODO: Automatiser le payout via FedaPay
            // $this->processPayout($withdrawal);

            DB::commit();

            Log::info('✅ Retrait approuvé', [
                'withdrawal_id' => $withdrawal->id,
                'admin_id' => $request->user()->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Demande approuvée',
                'withdrawal' => $withdrawal->fresh(['formateur', 'processedBy']),
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('❌ Erreur approve', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'approbation',
            ], 500);
        }
    }

    /**
     * Rejeter une demande de retrait
     */
    public function reject(Request $request, FormateurWithdrawal $withdrawal)
    {
        if (!$request->user()->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé',
            ], 403);
        }

        try {
            $request->validate([
                'admin_notes' => 'required|string|max:500',
            ]);

            if (!$withdrawal->isPending()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cette demande a déjà été traitée',
                ], 400);
            }

            DB::beginTransaction();

            // Rejeter
            $withdrawal->reject($request->user()->id, $request->admin_notes);

            // Notifier le formateur
            $this->notificationService->creer(
                $withdrawal->formateur_id,
                'retrait_rejete',
                '❌ Retrait rejeté',
                "Votre demande de retrait de {$withdrawal->montant_demande} FCFA a été rejetée. Raison: {$request->admin_notes}",
                '/formateur/revenus',
                [
                    'withdrawal_id' => $withdrawal->id,
                    'raison' => $request->admin_notes,
                ]
            );

            DB::commit();

            Log::info('🚫 Retrait rejeté', [
                'withdrawal_id' => $withdrawal->id,
                'admin_id' => $request->user()->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Demande rejetée',
                'withdrawal' => $withdrawal->fresh(['formateur', 'processedBy']),
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('❌ Erreur reject', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du rejet',
            ], 500);
        }
    }

    /**
     * Marquer comme complété (paiement effectué)
     */
    public function markAsCompleted(Request $request, FormateurWithdrawal $withdrawal)
    {
        if (!$request->user()->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé',
            ], 403);
        }

        try {
            if ($withdrawal->statut !== 'approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'Seules les demandes approuvées peuvent être marquées comme complétées',
                ], 400);
            }

            DB::beginTransaction();

            // Marquer comme complété
            $withdrawal->markAsCompleted();

            // Notifier le formateur
            $this->notificationService->creer(
                $withdrawal->formateur_id,
                'retrait_complete',
                '🎉 Retrait complété',
                "Votre retrait de {$withdrawal->montant_demande} FCFA a été effectué avec succès sur votre numéro {$withdrawal->phone_number}.",
                '/formateur/revenus',
                [
                    'withdrawal_id' => $withdrawal->id,
                    'montant' => $withdrawal->montant_demande,
                ]
            );

            DB::commit();

            Log::info('✅ Retrait marqué complété', [
                'withdrawal_id' => $withdrawal->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Retrait marqué comme complété',
                'withdrawal' => $withdrawal->fresh(['formateur', 'processedBy']),
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('❌ Erreur markAsCompleted', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour',
            ], 500);
        }
    }

    /**
     * Supprimer une demande
     */
    public function destroy(Request $request, FormateurWithdrawal $withdrawal)
    {
        if (!$request->user()->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé',
            ], 403);
        }

        try {
            $withdrawal->delete();

            Log::info('🗑️ Demande de retrait supprimée', [
                'withdrawal_id' => $withdrawal->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Demande supprimée',
            ], 200);

        } catch (\Exception $e) {
            Log::error('❌ Erreur destroy', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression',
            ], 500);
        }
    }
}