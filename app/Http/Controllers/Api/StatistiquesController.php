<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Formation;
use App\Models\Inscription;
use App\Models\Paiement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class StatistiquesController extends Controller
{
    /**
     * Statistiques globales du formateur
     */
    public function index(Request $request)
    {
        try {
            $formateur = $request->user();
            
            Log::info('📊 Chargement statistiques formateur', [
                'formateur_id' => $formateur->id,
            ]);
            
            $formations = $formateur->formationsCreees;

            if (!$formations) {
                return response()->json([
                    'success' => true,
                    'statistiques' => $this->getEmptyStats(),
                ], 200);
            }

            $stats = [
                'total_formations' => $formations->count(),
                'formations_publiees' => $formations->where('statut', 'publie')->count(),
                'formations_brouillon' => $formations->where('statut', 'brouillon')->count(),
                'formations_archivees' => $formations->where('statut', 'archive')->count(),
                
                'total_apprenants' => $formateur->totalApprenants(),
                'revenus_total' => $formateur->revenusTotal(),
                
                'demandes_en_attente' => Inscription::whereIn('formation_id', $formations->pluck('id'))
                    ->where('statut', 'en_attente')
                    ->count(),
            ];

            Log::info('✅ Statistiques chargées', $stats);

            return response()->json([
                'success' => true,
                'statistiques' => $stats,
            ], 200);

        } catch (\Exception $e) {
            Log::error('❌ Erreur StatistiquesController@index', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des statistiques',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Revenus détaillés - CORRIGÉ
     */
    public function revenus(Request $request)
    {
        try {
            $formateur = $request->user();
            
            Log::info('💰 Chargement revenus formateur', [
                'formateur_id' => $formateur->id,
            ]);
            
            $formations = $formateur->formationsCreees;

            if (!$formations || $formations->isEmpty()) {
                Log::info('ℹ️ Aucune formation trouvée pour ce formateur');
                
                return response()->json([
                    'success' => true,
                    'revenus_total' => 0,
                    'revenus_par_formation' => [],
                ], 200);
            }

            $revenusParFormation = [];
            $revenusTotal = 0;

            foreach ($formations as $formation) {
                try {
                    // Récupérer les paiements complétés pour cette formation
                    $paiements = Paiement::where('formation_id', $formation->id)
                        ->where('statut', 'complete')
                        ->get();
                    
                    $revenus = $paiements->sum('montant');
                    $nombreVentes = $paiements->count();
                    
                    // Calculer la commission (10% par défaut)
                    $commission = ($formation->commission_admin ?? 10) / 100;
                    $revenusNet = $revenus * (1 - $commission);
                    
                    $revenusParFormation[] = [
                        'formation_id' => $formation->id,
                        'formation_titre' => $formation->titre,
                        'revenus' => round($revenus, 2),
                        'revenus_net' => round($revenusNet, 2),
                        'commission' => round($revenus * $commission, 2),
                        'nombre_ventes' => $nombreVentes,
                    ];
                    
                    $revenusTotal += $revenus;
                    
                } catch (\Exception $e) {
                    Log::warning('⚠️ Erreur calcul revenus formation', [
                        'formation_id' => $formation->id,
                        'error' => $e->getMessage(),
                    ]);
                    continue;
                }
            }

            Log::info('✅ Revenus calculés', [
                'total' => $revenusTotal,
                'nb_formations' => count($revenusParFormation),
            ]);

            return response()->json([
                'success' => true,
                'revenus_total' => round($revenusTotal, 2),
                'revenus_par_formation' => $revenusParFormation,
            ], 200);

        } catch (\Exception $e) {
            Log::error('❌ Erreur StatistiquesController@revenus', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des revenus',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Apprenants par formation
     */
    public function apprenants(Request $request)
    {
        try {
            $formateur = $request->user();
            
            Log::info('👥 Chargement apprenants formateur', [
                'formateur_id' => $formateur->id,
            ]);
            
            $formations = $formateur->formationsCreees;

            if (!$formations || $formations->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'apprenants_par_formation' => [],
                    'total_apprenants_unique' => 0,
                ], 200);
            }

            $apprenantsParFormation = [];

            foreach ($formations as $formation) {
                try {
                    $inscriptions = $formation->inscriptions();
                    
                    $apprenantsParFormation[] = [
                        'formation_id' => $formation->id,
                        'formation_titre' => $formation->titre,
                        'total_apprenants' => $inscriptions->whereIn('statut', ['active', 'approuvee', 'en_cours', 'terminee'])->count(),
                        'apprenants_actifs' => $inscriptions->where('statut', 'active')->count(),
                        'apprenants_bloques' => $inscriptions->where('is_blocked', true)->count(),
                        'demandes_en_attente' => $inscriptions->where('statut', 'en_attente')->count(),
                        'progression_moyenne' => round($inscriptions->where('statut', 'active')->avg('progres') ?? 0, 2),
                    ];
                    
                } catch (\Exception $e) {
                    Log::warning('⚠️ Erreur calcul apprenants formation', [
                        'formation_id' => $formation->id,
                        'error' => $e->getMessage(),
                    ]);
                    continue;
                }
            }

            Log::info('✅ Apprenants calculés', [
                'nb_formations' => count($apprenantsParFormation),
            ]);

            return response()->json([
                'success' => true,
                'apprenants_par_formation' => $apprenantsParFormation,
                'total_apprenants_unique' => $formateur->totalApprenants(),
            ], 200);

        } catch (\Exception $e) {
            Log::error('❌ Erreur StatistiquesController@apprenants', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des apprenants',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Demandes d'accès en attente
     */
    public function demandesEnAttente(Request $request)
    {
        try {
            $formateur = $request->user();
            $formations = $formateur->formationsCreees;

            if (!$formations || $formations->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'demandes' => [],
                ], 200);
            }

            $demandes = Inscription::whereIn('formation_id', $formations->pluck('id'))
                ->where('statut', 'en_attente')
                ->with(['user', 'formation'])
                ->latest()
                ->get();

            return response()->json([
                'success' => true,
                'demandes' => $demandes,
            ], 200);

        } catch (\Exception $e) {
            Log::error('❌ Erreur demandesEnAttente', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des demandes',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Graphique des inscriptions par mois
     */
    public function graphiqueInscriptions(Request $request)
    {
        try {
            $formateur = $request->user();
            $formations = $formateur->formationsCreees;

            if (!$formations || $formations->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'graphique' => [],
                ], 200);
            }

            $inscriptions = Inscription::whereIn('formation_id', $formations->pluck('id'))
                ->whereIn('statut', ['active', 'approuvee'])
                ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as mois, COUNT(*) as total')
                ->groupBy('mois')
                ->orderBy('mois')
                ->get();

            return response()->json([
                'success' => true,
                'graphique' => $inscriptions,
            ], 200);

        } catch (\Exception $e) {
            Log::error('❌ Erreur graphiqueInscriptions', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement du graphique',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Statistiques vides par défaut
     */
    private function getEmptyStats()
    {
        return [
            'total_formations' => 0,
            'formations_publiees' => 0,
            'formations_brouillon' => 0,
            'formations_archivees' => 0,
            'total_apprenants' => 0,
            'revenus_total' => 0,
            'demandes_en_attente' => 0,
        ];
    }
}