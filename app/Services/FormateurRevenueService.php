<?php

namespace App\Services;

use App\Models\User;
use App\Models\Paiement;
use App\Models\FormateurWithdrawal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FormateurRevenueService
{
    /**
     * Calculer le solde disponible d'un formateur
     */
    public function calculateAvailableBalance($formateurId)
    {
        try {
            // Total des revenus (90% de chaque vente)
            $totalRevenus = Paiement::where('statut', 'complete')
                ->whereHas('formation', function($query) use ($formateurId) {
                    $query->where('formateur_id', $formateurId);
                })
                ->get()
                ->sum(function($paiement) {
                    $commission = ($paiement->formation->commission_admin ?? 10) / 100;
                    return $paiement->montant * (1 - $commission);
                });

            // Total des retraits déjà effectués (approved + completed)
            $totalRetraits = FormateurWithdrawal::where('formateur_id', $formateurId)
                ->whereIn('statut', ['approved', 'completed'])
                ->sum('montant_demande');

            $soldeDisponible = $totalRevenus - $totalRetraits;

            Log::info('💰 Calcul solde formateur', [
                'formateur_id' => $formateurId,
                'total_revenus' => $totalRevenus,
                'total_retraits' => $totalRetraits,
                'solde_disponible' => $soldeDisponible,
            ]);

            return [
                'total_revenus' => round($totalRevenus, 2),
                'total_retraits' => round($totalRetraits, 2),
                'solde_disponible' => round($soldeDisponible, 2),
            ];

        } catch (\Exception $e) {
            Log::error('❌ Erreur calculateAvailableBalance', [
                'message' => $e->getMessage(),
            ]);

            return [
                'total_revenus' => 0,
                'total_retraits' => 0,
                'solde_disponible' => 0,
            ];
        }
    }

    /**
     * Vérifier si un retrait est possible
     */
    public function canWithdraw($formateurId, $montantDemande)
    {
        $balance = $this->calculateAvailableBalance($formateurId);
        
        return [
            'can_withdraw' => $balance['solde_disponible'] >= $montantDemande,
            'solde_disponible' => $balance['solde_disponible'],
            'montant_manquant' => max(0, $montantDemande - $balance['solde_disponible']),
        ];
    }

    /**
     * Obtenir l'historique des retraits
     */
    public function getWithdrawalHistory($formateurId, $limit = 20)
    {
        return FormateurWithdrawal::where('formateur_id', $formateurId)
            ->with('processedBy')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Obtenir les statistiques de revenus
     */
    public function getRevenueStats($formateurId)
    {
        $balance = $this->calculateAvailableBalance($formateurId);
        
        $retraitsPending = FormateurWithdrawal::where('formateur_id', $formateurId)
            ->where('statut', 'pending')
            ->sum('montant_demande');

        $retraitsCompleted = FormateurWithdrawal::where('formateur_id', $formateurId)
            ->where('statut', 'completed')
            ->count();

        return array_merge($balance, [
            'retraits_en_attente' => round($retraitsPending, 2),
            'nombre_retraits_completes' => $retraitsCompleted,
        ]);
    }
}