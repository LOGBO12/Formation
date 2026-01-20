<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inscription;
use App\Models\Formation;
use App\Models\Chapitre;
use Illuminate\Http\Request;

class ApprenantController extends Controller
{
    /**
     * Mes formations (inscriptions actives)
     */
    public function mesFormations(Request $request)
    {
        $inscriptions = Inscription::where('user_id', $request->user()->id)
            ->whereIn('statut', ['active', 'approuvee'])
            ->where('is_blocked', false)
            ->with([
                'formation.domaine',
                'formation.formateur',
                'formation.modules.chapitres'
            ])
            ->latest()
            ->get();

        // Calculer la progression pour chaque formation
        $formations = $inscriptions->map(function ($inscription) {
            $inscription->calculerProgression();
            return [
                'inscription_id' => $inscription->id,
                'formation' => $inscription->formation,
                'progres' => $inscription->progres,
                'date_inscription' => $inscription->created_at,
                'derniere_activite' => $inscription->updated_at,
                'is_completed' => $inscription->progres >= 100,
            ];
        });

        return response()->json([
            'success' => true,
            'formations' => $formations,
        ]);
    }

    /**
     * Formations terminées
     */
    public function formationsTerminees(Request $request)
    {
        $inscriptions = Inscription::where('user_id', $request->user()->id)
            ->whereIn('statut', ['active', 'approuvee'])
            ->where('progres', 100)
            ->with(['formation.domaine', 'formation.formateur'])
            ->get();

        return response()->json([
            'success' => true,
            'formations' => $inscriptions,
        ]);
    }

    /**
     * Contenu d'une formation (après inscription)
     * Middleware CheckInscription appliqué dans les routes
     */
    public function contenuFormation(Request $request, Formation $formation)
    {
        // Charger toutes les relations nécessaires
        $formation->load([
            'domaine',
            'formateur',
            'modules.chapitres' => function ($query) {
                $query->orderBy('ordre');
            },
            'communaute'
        ]);

        // Récupérer l'inscription de l'utilisateur
        $inscription = Inscription::where('user_id', $request->user()->id)
            ->where('formation_id', $formation->id)
            ->first();

        // Récupérer la progression pour chaque chapitre
        $formation->modules->each(function ($module) use ($request) {
            $module->chapitres->each(function ($chapitre) use ($request) {
                $chapitre->is_completed = $chapitre->estCompletePar($request->user()->id);
            });
        });

        return response()->json([
            'success' => true,
            'formation' => $formation,
            'inscription' => $inscription,
            'progres_global' => $inscription->progres,
        ]);
    }

    /**
     * Lire un chapitre spécifique
     * Middleware CheckInscription appliqué dans les routes
     */
    public function lireChapitre(Request $request, Chapitre $chapitre)
    {
        // Charger les relations
        $chapitre->load(['module.formation', 'quiz.questions.options']);

        // Vérifier si le chapitre est déjà complété
        $progression = $chapitre->progressions()
            ->where('user_id', $request->user()->id)
            ->first();

        $chapitre->is_completed = $progression ? $progression->is_completed : false;
        $chapitre->date_completion = $progression ? $progression->date_completion : null;

        // Si c'est un quiz, charger les résultats de l'utilisateur
        if ($chapitre->type === 'quiz' && $chapitre->quiz) {
            $chapitre->quiz->mes_resultats = $chapitre->quiz->resultats()
                ->where('user_id', $request->user()->id)
                ->latest()
                ->get();
        }

        // Récupérer le chapitre précédent et suivant
        $chapitres = $chapitre->module->chapitres()->orderBy('ordre')->get();
        $currentIndex = $chapitres->search(function ($item) use ($chapitre) {
            return $item->id === $chapitre->id;
        });

        $chapitre->chapitre_precedent = $currentIndex > 0 ? $chapitres[$currentIndex - 1] : null;
        $chapitre->chapitre_suivant = $currentIndex < $chapitres->count() - 1 ? $chapitres[$currentIndex + 1] : null;

        return response()->json([
            'success' => true,
            'chapitre' => $chapitre,
        ]);
    }

    /**
     * Marquer un chapitre comme terminé
     */
    public function terminerChapitre(Request $request, Chapitre $chapitre)
    {
        // Vérifier que l'apprenant a accès à cette formation
        $formation = $chapitre->module->formation;
        $inscription = Inscription::where('user_id', $request->user()->id)
            ->where('formation_id', $formation->id)
            ->whereIn('statut', ['active', 'approuvee'])
            ->where('is_blocked', false)
            ->first();

        if (!$inscription) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'avez pas accès à cette formation',
            ], 403);
        }

        // Marquer le chapitre comme complété
        $progression = $chapitre->progressions()->updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'is_completed' => true,
                'date_completion' => now(),
            ]
        );

        // Recalculer la progression de la formation
        $nouveauProgres = $inscription->calculerProgression();

        return response()->json([
            'success' => true,
            'message' => 'Chapitre marqué comme terminé',
            'progression' => $progression,
            'progres_formation' => $nouveauProgres,
        ]);
    }

    /**
     * Ma progression détaillée dans une formation
     */
    public function progressionFormation(Request $request, Formation $formation)
    {
        $inscription = Inscription::where('user_id', $request->user()->id)
            ->where('formation_id', $formation->id)
            ->first();

        if (!$inscription) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas inscrit à cette formation',
            ], 404);
        }

        $totalChapitres = 0;
        $chapitresCompletes = 0;
        $detailModules = [];

        foreach ($formation->modules as $module) {
            $moduleTotal = $module->chapitres->count();
            $moduleCompletes = 0;

            foreach ($module->chapitres as $chapitre) {
                $totalChapitres++;
                if ($chapitre->estCompletePar($request->user()->id)) {
                    $chapitresCompletes++;
                    $moduleCompletes++;
                }
            }

            $detailModules[] = [
                'module_id' => $module->id,
                'module_titre' => $module->titre,
                'total_chapitres' => $moduleTotal,
                'chapitres_completes' => $moduleCompletes,
                'progres' => $moduleTotal > 0 ? ($moduleCompletes / $moduleTotal) * 100 : 0,
            ];
        }

        return response()->json([
            'success' => true,
            'progres_global' => $inscription->progres,
            'total_chapitres' => $totalChapitres,
            'chapitres_completes' => $chapitresCompletes,
            'modules' => $detailModules,
        ]);
    }

    /**
     * Statistiques de l'apprenant
     */
    public function statistiques(Request $request)
    {
        $userId = $request->user()->id;

        $stats = [
            'formations_en_cours' => Inscription::where('user_id', $userId)
                ->whereIn('statut', ['active', 'approuvee'])
                ->where('progres', '<', 100)
                ->count(),
            
            'formations_terminees' => Inscription::where('user_id', $userId)
                ->whereIn('statut', ['active', 'approuvee'])
                ->where('progres', 100)
                ->count(),
            
            'total_heures_apprentissage' => Inscription::where('user_id', $userId)
                ->whereIn('statut', ['active', 'approuvee'])
                ->join('formations', 'inscriptions.formation_id', '=', 'formations.id')
                ->sum('formations.duree_estimee'),
            
            'progression_moyenne' => round(
                Inscription::where('user_id', $userId)
                    ->whereIn('statut', ['active', 'approuvee'])
                    ->avg('progres') ?? 0,
                2
            ),
        ];

        return response()->json([
            'success' => true,
            'statistiques' => $stats,
        ]);
    }
}