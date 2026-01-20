<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Formation;
use App\Models\Inscription;
use App\Models\Paiement;
use Illuminate\Http\Request;

class StatistiquesController extends Controller
{
    /**
     * Statistiques globales du formateur
     */
    public function index(Request $request)
    {
        $formateur = $request->user();
        $formations = $formateur->formationsCreees;

        $stats = [
            'total_formations' => $formations->count(),
            'formations_publiees' => $formations->where('statut', 'publie')->count(),
            'formations_brouillon' => $formations->where('statut', 'brouillon')->count(),
            'formations_archivees' => $formations->where('statut', 'archive')->count(),
            
            'total_apprenants' => $formateur->totalApprenants(),
            'revenus_total' => $formateur->revenusTotal(),
            
            'demandes_en_attente' => Inscription::whereIn('formation_id', $formations->pluck('id'))
                ->enAttente()
                ->count(),
        ];

        return response()->json([
            'success' => true,
            'statistiques' => $stats,
        ]);
    }

    /**
     * Revenus détaillés
     */
    public function revenus(Request $request)
    {
        $formateur = $request->user();
        $formations = $formateur->formationsCreees;

        $revenusParFormation = [];

        foreach ($formations as $formation) {
            $revenusParFormation[] = [
                'formation_id' => $formation->id,
                'formation_titre' => $formation->titre,
                'revenus' => $formation->totalRevenus(),
                'nombre_ventes' => $formation->paiements()->completes()->count(),
            ];
        }

        $revenusTotal = $formateur->revenusTotal();

        return response()->json([
            'success' => true,
            'revenus_total' => $revenusTotal,
            'revenus_par_formation' => $revenusParFormation,
        ]);
    }

    /**
     * Apprenants par formation
     */
    public function apprenants(Request $request)
    {
        $formateur = $request->user();
        $formations = $formateur->formationsCreees;

        $apprenantsParFormation = [];

        foreach ($formations as $formation) {
            $apprenantsParFormation[] = [
                'formation_id' => $formation->id,
                'formation_titre' => $formation->titre,
                'total_apprenants' => $formation->totalApprenants(),
                'apprenants_actifs' => $formation->inscriptions()->actives()->count(),
                'apprenants_bloques' => $formation->inscriptions()->bloquees()->count(),
                'demandes_en_attente' => $formation->inscriptions()->enAttente()->count(),
                'progression_moyenne' => round($formation->inscriptions()->actives()->avg('progres') ?? 0, 2),
            ];
        }

        return response()->json([
            'success' => true,
            'apprenants_par_formation' => $apprenantsParFormation,
            'total_apprenants_unique' => $formateur->totalApprenants(),
        ]);
    }

    /**
     * Demandes d'accès en attente
     */
    public function demandesEnAttente(Request $request)
    {
        $formateur = $request->user();
        $formations = $formateur->formationsCreees;

        $demandes = Inscription::whereIn('formation_id', $formations->pluck('id'))
            ->enAttente()
            ->with(['user', 'formation'])
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'demandes' => $demandes,
        ]);
    }

    /**
     * Graphique des inscriptions par mois
     */
    public function graphiqueInscriptions(Request $request)
    {
        $formateur = $request->user();
        $formations = $formateur->formationsCreees;

        $inscriptions = Inscription::whereIn('formation_id', $formations->pluck('id'))
            ->whereIn('statut', ['active', 'approuvee'])
            ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as mois, COUNT(*) as total')
            ->groupBy('mois')
            ->orderBy('mois')
            ->get();

        return response()->json([
            'success' => true,
            'graphique' => $inscriptions,
        ]);
    }
}