<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Paiement;
use App\Models\Formation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AdminRevenusController extends Controller
{
    /**
     * Liste des paiements avec statistiques
     */
    public function index(Request $request)
    {
        try {
            $status = $request->get('status', 'all');
            $period = $request->get('period', 'all');
            $search = $request->get('search', '');

            Log::info('📊 Admin Revenus - Requête reçue', [
                'status' => $status,
                'period' => $period,
                'search' => $search,
            ]);

            // Query de base
            $query = Paiement::with(['user', 'formation.domaine', 'formation.formateur']);

            // Filtre par statut
            if ($status !== 'all') {
                $query->where('statut', $status);
            }

            // Filtre par période
            if ($period !== 'all') {
                $query->where('created_at', '>=', $this->getPeriodDate($period));
            }

            // Recherche
            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->whereHas('user', function ($q2) use ($search) {
                        $q2->where('name', 'like', "%{$search}%")
                           ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('formation', function ($q2) use ($search) {
                        $q2->where('titre', 'like', "%{$search}%");
                    })
                    ->orWhereHas('formation.formateur', function ($q2) use ($search) {
                        $q2->where('name', 'like', "%{$search}%");
                    });
                });
            }

            // Récupérer les paiements
            $paiements = $query->orderBy('created_at', 'desc')->get();

            // Calculer les statistiques
            $stats = $this->calculateStats($period);

            Log::info('✅ Admin Revenus - Données chargées', [
                'total_paiements' => $paiements->count(),
                'total_revenus' => $stats['total_revenus'],
            ]);

            return response()->json([
                'success' => true,
                'paiements' => $paiements,
                'stats' => $stats,
            ], 200);

        } catch (\Exception $e) {
            Log::error('❌ Erreur AdminRevenusController@index', [
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
     * Calculer les statistiques
     */
    private function calculateStats($period = 'all')
    {
        try {
            // Total revenus (tous les temps)
            $totalRevenus = Paiement::where('statut', 'complete')->sum('montant');

            // Revenus du mois en cours
            $revenuMois = Paiement::where('statut', 'complete')
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum('montant');

            // Revenus du mois précédent pour calculer l'évolution
            $revenuMoisPrecedent = Paiement::where('statut', 'complete')
                ->whereMonth('created_at', now()->subMonth()->month)
                ->whereYear('created_at', now()->subMonth()->year)
                ->sum('montant');

            // Évolution en pourcentage
            $evolutionMois = 0;
            if ($revenuMoisPrecedent > 0) {
                $evolutionMois = round((($revenuMois - $revenuMoisPrecedent) / $revenuMoisPrecedent) * 100, 2);
            } elseif ($revenuMois > 0) {
                $evolutionMois = 100;
            }

            // Total transactions
            $totalTransactions = Paiement::where('statut', 'complete')->count();

            // Revenus aujourd'hui
            $revenuAujourdhui = Paiement::where('statut', 'complete')
                ->whereDate('created_at', today())
                ->sum('montant');

            // Revenus cette semaine
            $revenuSemaine = Paiement::where('statut', 'complete')
                ->whereBetween('created_at', [
                    now()->startOfWeek(),
                    now()->endOfWeek(),
                ])
                ->sum('montant');

            return [
                'total_revenus' => round($totalRevenus, 2),
                'revenu_mois' => round($revenuMois, 2),
                'evolution_mois' => $evolutionMois,
                'total_transactions' => $totalTransactions,
                'revenu_aujourd_hui' => round($revenuAujourdhui, 2),
                'revenu_semaine' => round($revenuSemaine, 2),
            ];

        } catch (\Exception $e) {
            Log::error('❌ Erreur calculateStats', [
                'message' => $e->getMessage(),
            ]);

            return [
                'total_revenus' => 0,
                'revenu_mois' => 0,
                'evolution_mois' => 0,
                'total_transactions' => 0,
                'revenu_aujourd_hui' => 0,
                'revenu_semaine' => 0,
            ];
        }
    }

    /**
     * Obtenir la date de début selon la période
     */
    private function getPeriodDate($period)
    {
        switch ($period) {
            case 'today':
                return Carbon::today();
            case 'week':
                return Carbon::now()->startOfWeek();
            case 'month':
                return Carbon::now()->startOfMonth();
            case 'year':
                return Carbon::now()->startOfYear();
            default:
                return Carbon::now()->subYears(10); // Tous les temps
        }
    }

    /**
     * Exporter les données
     */
    public function export(Request $request)
    {
        try {
            $status = $request->get('status', 'all');
            $period = $request->get('period', 'all');

            // Query de base
            $query = Paiement::with(['user', 'formation.formateur']);

            // Filtres
            if ($status !== 'all') {
                $query->where('statut', $status);
            }

            if ($period !== 'all') {
                $query->where('created_at', '>=', $this->getPeriodDate($period));
            }

            $paiements = $query->orderBy('created_at', 'desc')->get();

            // Préparer les données pour l'export
            $data = [];
            $data[] = ['Date', 'Apprenant', 'Email', 'Formation', 'Formateur', 'Montant', 'Commission', 'Statut']; // Header

            foreach ($paiements as $paiement) {
                $commission = ($paiement->formation->commission_admin ?? 10) / 100 * $paiement->montant;
                
                $data[] = [
                    $paiement->created_at->format('Y-m-d H:i'),
                    $paiement->user->name ?? 'N/A',
                    $paiement->user->email ?? 'N/A',
                    $paiement->formation->titre ?? 'N/A',
                    $paiement->formation->formateur->name ?? 'N/A',
                    $paiement->montant,
                    $commission,
                    $paiement->statut,
                ];
            }

            Log::info('✅ Export revenus généré', [
                'total_lignes' => count($data),
            ]);

            return response()->json([
                'success' => true,
                'data' => $data,
                'count' => count($data) - 1, // Sans le header
            ], 200);

        } catch (\Exception $e) {
            Log::error('❌ Erreur export revenus', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'export',
            ], 500);
        }
    }

    /**
     * Statistiques détaillées
     */
    public function statistics(Request $request)
    {
        try {
            $stats = [
                'revenus_par_mois' => $this->getRevenuParMois(),
                'revenus_par_formation' => $this->getRevenuParFormation(),
                'revenus_par_formateur' => $this->getRevenuParFormateur(),
                'top_formations' => $this->getTopFormations(),
            ];

            return response()->json([
                'success' => true,
                'statistics' => $stats,
            ], 200);

        } catch (\Exception $e) {
            Log::error('❌ Erreur statistics', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des statistiques',
            ], 500);
        }
    }

    /**
     * Revenus par mois (12 derniers mois)
     */
    private function getRevenuParMois()
    {
        $revenus = Paiement::where('statut', 'complete')
            ->where('created_at', '>=', now()->subMonths(12))
            ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as mois, SUM(montant) as total')
            ->groupBy('mois')
            ->orderBy('mois')
            ->get();

        return $revenus;
    }

    /**
     * Revenus par formation (top 10)
     */
    private function getRevenuParFormation()
    {
        $revenus = Paiement::where('statut', 'complete')
            ->with('formation')
            ->select('formation_id', DB::raw('SUM(montant) as total'), DB::raw('COUNT(*) as nombre_ventes'))
            ->groupBy('formation_id')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    'formation_id' => $item->formation_id,
                    'formation_titre' => $item->formation->titre ?? 'N/A',
                    'revenus' => round($item->total, 2),
                    'nombre_ventes' => $item->nombre_ventes,
                ];
            });

        return $revenus;
    }

    /**
     * Revenus par formateur (top 10)
     */
    private function getRevenuParFormateur()
    {
        $revenus = Paiement::where('statut', 'complete')
            ->with('formation.formateur')
            ->select('formation_id', DB::raw('SUM(montant) as total'))
            ->groupBy('formation_id')
            ->get()
            ->groupBy(function ($item) {
                return $item->formation->formateur_id ?? 'N/A';
            })
            ->map(function ($items, $formateurId) {
                $formateur = User::find($formateurId);
                return [
                    'formateur_id' => $formateurId,
                    'formateur_nom' => $formateur->name ?? 'N/A',
                    'revenus' => round($items->sum('total'), 2),
                    'nombre_formations' => $items->count(),
                ];
            })
            ->sortByDesc('revenus')
            ->take(10)
            ->values();

        return $revenus;
    }

    /**
     * Top formations par nombre de ventes
     */
    private function getTopFormations()
    {
        $top = Paiement::where('statut', 'complete')
            ->with('formation.formateur')
            ->select('formation_id', DB::raw('COUNT(*) as nombre_ventes'), DB::raw('SUM(montant) as revenus'))
            ->groupBy('formation_id')
            ->orderByDesc('nombre_ventes')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                return [
                    'formation_id' => $item->formation_id,
                    'formation_titre' => $item->formation->titre ?? 'N/A',
                    'formateur_nom' => $item->formation->formateur->name ?? 'N/A',
                    'nombre_ventes' => $item->nombre_ventes,
                    'revenus' => round($item->revenus, 2),
                ];
            });

        return $top;
    }
}