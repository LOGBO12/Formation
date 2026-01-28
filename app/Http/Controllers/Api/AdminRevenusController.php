<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Paiement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminRevenusController extends Controller
{
    /**
     * Vue d'ensemble des revenus
     */
    public function index(Request $request)
    {
        if (!$request->user()->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé',
            ], 403);
        }

        $status = $request->input('status', 'all');
        $period = $request->input('period', 'all');
        $search = $request->input('search', '');

        $query = Paiement::with(['apprenant:id,name,email', 'formation:id,titre', 'formateur:id,name'])
            ->orderBy('created_at', 'desc');

        // Filtre par statut
        if ($status !== 'all') {
            $query->where('statut', $status);
        }

        // Filtre par période
        switch ($period) {
            case 'today':
                $query->whereDate('created_at', today());
                break;
            case 'week':
                $query->whereBetween('created_at', [
                    now()->startOfWeek(),
                    now()->endOfWeek()
                ]);
                break;
            case 'month':
                $query->whereMonth('created_at', now()->month)
                      ->whereYear('created_at', now()->year);
                break;
            case 'year':
                $query->whereYear('created_at', now()->year);
                break;
        }

        // Recherche
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('apprenant', function ($sq) use ($search) {
                    $sq->where('name', 'like', "%{$search}%")
                       ->orWhere('email', 'like', "%{$search}%");
                })
                ->orWhereHas('formation', function ($sq) use ($search) {
                    $sq->where('titre', 'like', "%{$search}%");
                })
                ->orWhereHas('formateur', function ($sq) use ($search) {
                    $sq->where('name', 'like', "%{$search}%");
                });
            });
        }

        $paiements = $query->paginate(50);

        // Statistiques
        $stats = [
            'total_revenus' => Paiement::where('statut', 'completed')->sum('montant'),
            'revenu_mois' => Paiement::where('statut', 'completed')
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum('montant'),
            'revenu_semaine' => Paiement::where('statut', 'completed')
                ->whereBetween('created_at', [
                    now()->startOfWeek(),
                    now()->endOfWeek()
                ])
                ->sum('montant'),
            'revenu_aujourd_hui' => Paiement::where('statut', 'completed')
                ->whereDate('created_at', today())
                ->sum('montant'),
            'total_transactions' => Paiement::where('statut', 'completed')->count(),
            'evolution_mois' => $this->calculateEvolutionMois(),
        ];

        return response()->json([
            'success' => true,
            'paiements' => $paiements,
            'stats' => $stats,
        ]);
    }

    /**
     * Exporter les revenus en CSV
     */
    public function export(Request $request)
    {
        if (!$request->user()->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé',
            ], 403);
        }

        $status = $request->input('status', 'all');
        $period = $request->input('period', 'all');

        $query = Paiement::with(['apprenant', 'formation', 'formateur']);

        // Appliquer les mêmes filtres
        if ($status !== 'all') {
            $query->where('statut', $status);
        }

        switch ($period) {
            case 'today':
                $query->whereDate('created_at', today());
                break;
            case 'week':
                $query->whereBetween('created_at', [
                    now()->startOfWeek(),
                    now()->endOfWeek()
                ]);
                break;
            case 'month':
                $query->whereMonth('created_at', now()->month)
                      ->whereYear('created_at', now()->year);
                break;
            case 'year':
                $query->whereYear('created_at', now()->year);
                break;
        }

        $paiements = $query->get();

        $data = $paiements->map(function ($p) {
            return [
                'Date' => $p->created_at->format('Y-m-d H:i:s'),
                'Apprenant' => $p->apprenant->name ?? 'N/A',
                'Email Apprenant' => $p->apprenant->email ?? 'N/A',
                'Formation' => $p->formation->titre ?? 'N/A',
                'Formateur' => $p->formateur->name ?? 'N/A',
                'Montant' => $p->montant,
                'Commission (10%)' => $p->montant * 0.1,
                'Part Formateur (90%)' => $p->montant * 0.9,
                'Statut' => $p->statut,
                'Méthode' => $p->methode_paiement ?? 'Mobile Money',
                'Transaction ID' => $p->transaction_id ?? 'N/A',
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Statistiques détaillées
     */
    public function statistics(Request $request)
    {
        if (!$request->user()->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé',
            ], 403);
        }

        // Revenus par mois (12 derniers mois)
        $revenusParMois = Paiement::where('statut', 'completed')
            ->where('created_at', '>=', now()->subMonths(12))
            ->select(
                DB::raw('YEAR(created_at) as year'),
                DB::raw('MONTH(created_at) as month'),
                DB::raw('SUM(montant) as total')
            )
            ->groupBy('year', 'month')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get();

        // Top formateurs par revenus
        $topFormateurs = Paiement::where('statut', 'completed')
            ->select(
                'formateur_id',
                DB::raw('SUM(montant) as total_revenus'),
                DB::raw('COUNT(*) as nombre_ventes')
            )
            ->with('formateur:id,name,email')
            ->groupBy('formateur_id')
            ->orderBy('total_revenus', 'desc')
            ->limit(10)
            ->get();

        // Top formations par revenus
        $topFormations = Paiement::where('statut', 'completed')
            ->select(
                'formation_id',
                DB::raw('SUM(montant) as total_revenus'),
                DB::raw('COUNT(*) as nombre_ventes')
            )
            ->with('formation:id,titre')
            ->groupBy('formation_id')
            ->orderBy('total_revenus', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'revenus_par_mois' => $revenusParMois,
            'top_formateurs' => $topFormateurs,
            'top_formations' => $topFormations,
        ]);
    }

    /**
     * Calculer l'évolution du mois en cours par rapport au mois précédent
     */
    private function calculateEvolutionMois()
    {
        $moisActuel = Paiement::where('statut', 'completed')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('montant');

        $moisPrecedent = Paiement::where('statut', 'completed')
            ->whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)
            ->sum('montant');

        if ($moisPrecedent == 0) {
            return $moisActuel > 0 ? 100 : 0;
        }

        return round((($moisActuel - $moisPrecedent) / $moisPrecedent) * 100, 2);
    }
}