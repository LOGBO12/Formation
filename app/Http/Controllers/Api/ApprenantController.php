<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inscription;
use App\Models\Formation;
use App\Models\Chapitre;
use App\Models\Communaute;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApprenantController extends Controller
{
    /**
     * Dashboard - Statistiques de l'apprenant
     */
    public function dashboard(Request $request)
    {
        try {
            $userId = $request->user()->id;

            $stats = [
                'formations_en_cours' => Inscription::where('inscriptions.user_id', $userId)
                    ->whereIn('inscriptions.statut', ['active', 'approuvee'])
                    ->where('inscriptions.progres', '<', 100)
                    ->where('inscriptions.is_blocked', false)
                    ->count(),
                
                'formations_terminees' => Inscription::where('inscriptions.user_id', $userId)
                    ->whereIn('inscriptions.statut', ['active', 'approuvee'])
                    ->where('inscriptions.progres', 100)
                    ->count(),
                
                'total_heures_apprentissage' => Inscription::where('inscriptions.user_id', $userId)
                    ->whereIn('inscriptions.statut', ['active', 'approuvee'])
                    ->join('formations', 'inscriptions.formation_id', '=', 'formations.id')
                    ->sum('formations.duree_estimee') ?? 0,
                
                'progression_moyenne' => round(
                    Inscription::where('inscriptions.user_id', $userId)
                        ->whereIn('inscriptions.statut', ['active', 'approuvee'])
                        ->avg('inscriptions.progres') ?? 0,
                    2
                ),

                // Activité récente
                'dernieres_formations' => Inscription::where('inscriptions.user_id', $userId)
                    ->whereIn('inscriptions.statut', ['active', 'approuvee'])
                    ->with(['formation.domaine', 'formation.formateur'])
                    ->latest('inscriptions.updated_at')
                    ->take(3)
                    ->get()
                    ->map(function ($inscription) {
                        return [
                            'id' => $inscription->formation->id,
                            'titre' => $inscription->formation->titre,
                            'domaine' => $inscription->formation->domaine ? $inscription->formation->domaine->name : 'N/A',
                            'formateur' => $inscription->formation->formateur ? $inscription->formation->formateur->name : 'N/A',
                            'progres' => $inscription->progres,
                            'image' => $inscription->formation->image,
                            'updated_at' => $inscription->updated_at,
                        ];
                    }),

                // Communautés actives
                'communautes_actives' => Communaute::whereHas('membres', function($query) use ($userId) {
                    $query->where('user_id', $userId);
                })->count(),
            ];

            return response()->json([
                'success' => true,
                'statistiques' => $stats,
            ]);
        } catch (\Exception $e) {
            \Log::error('Erreur dashboard apprenant: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement du dashboard',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mes formations (inscriptions actives)
     */
    public function mesFormations(Request $request)
    {
        try {
            $inscriptions = Inscription::where('inscriptions.user_id', $request->user()->id)
                ->whereIn('inscriptions.statut', ['active', 'approuvee'])
                ->where('inscriptions.is_blocked', false)
                ->with([
                    'formation.domaine',
                    'formation.formateur',
                    'formation.modules.chapitres'
                ])
                ->latest('inscriptions.updated_at')
                ->get();

            // Calculer la progression pour chaque formation
            $formations = $inscriptions->map(function ($inscription) {
                $inscription->calculerProgression();
                
                $totalChapitres = 0;
                $chapitresCompletes = 0;
                
                foreach ($inscription->formation->modules as $module) {
                    $totalChapitres += $module->chapitres->count();
                    foreach ($module->chapitres as $chapitre) {
                        if ($chapitre->estCompletePar($inscription->user_id)) {
                            $chapitresCompletes++;
                        }
                    }
                }

                return [
                    'inscription_id' => $inscription->id,
                    'formation' => $inscription->formation,
                    'progres' => $inscription->progres,
                    'total_chapitres' => $totalChapitres,
                    'chapitres_completes' => $chapitresCompletes,
                    'date_inscription' => $inscription->created_at,
                    'derniere_activite' => $inscription->updated_at,
                    'is_completed' => $inscription->progres >= 100,
                ];
            });

            return response()->json([
                'success' => true,
                'formations' => $formations,
            ]);
        } catch (\Exception $e) {
            \Log::error('Erreur mes formations: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des formations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Catalogue des formations disponibles
     */
    public function catalogue(Request $request)
    {
        try {
            $userId = $request->user()->id;
            
            // Récupérer les IDs des formations déjà inscrites
            $formationsInscrites = Inscription::where('inscriptions.user_id', $userId)
                ->pluck('inscriptions.formation_id')
                ->toArray();

            // Récupérer les domaines de l'utilisateur
            $userDomaines = $request->user()->domaines->pluck('id')->toArray();

            // Formations disponibles (publiées et non inscrites)
            $query = Formation::where('formations.statut', 'publie')
                ->whereNotIn('formations.id', $formationsInscrites)
                ->with(['domaine', 'formateur'])
                ->withCount('inscriptions');

            // Filtres optionnels
            if ($request->has('domaine_id') && $request->domaine_id != '') {
                $query->where('formations.domaine_id', $request->domaine_id);
            }

            if ($request->has('is_free') && $request->is_free != '') {
                $query->where('formations.is_free', $request->is_free);
            }

            if ($request->has('search') && $request->search != '') {
                $query->where(function($q) use ($request) {
                    $q->where('formations.titre', 'like', '%' . $request->search . '%')
                      ->orWhere('formations.description', 'like', '%' . $request->search . '%');
                });
            }

            // Tri
            $sortBy = $request->get('sort_by', 'recent');
            switch ($sortBy) {
                case 'popular':
                    $query->orderBy('inscriptions_count', 'desc');
                    break;
                case 'price_asc':
                    $query->orderBy('formations.prix', 'asc');
                    break;
                case 'price_desc':
                    $query->orderBy('formations.prix', 'desc');
                    break;
                default:
                    $query->latest('formations.created_at');
            }

            $formations = $query->paginate(12);

            // Marquer les formations recommandées
            $formations->getCollection()->transform(function ($formation) use ($userDomaines) {
                $formation->is_recommended = in_array($formation->domaine_id, $userDomaines);
                return $formation;
            });

            return response()->json([
                'success' => true,
                'formations' => $formations,
            ]);
        } catch (\Exception $e) {
            \Log::error('Erreur catalogue: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement du catalogue',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ma progression globale
     */
    public function progression(Request $request)
    {
        try {
            $userId = $request->user()->id;

            $inscriptions = Inscription::where('inscriptions.user_id', $userId)
                ->whereIn('inscriptions.statut', ['active', 'approuvee'])
                ->with(['formation.domaine', 'formation.modules.chapitres'])
                ->get();

            $progressionParFormation = $inscriptions->map(function ($inscription) use ($userId) {
                $formation = $inscription->formation;
                $totalChapitres = 0;
                $chapitresCompletes = 0;
                $detailModules = [];

                foreach ($formation->modules as $module) {
                    $moduleTotal = $module->chapitres->count();
                    $moduleCompletes = 0;

                    foreach ($module->chapitres as $chapitre) {
                        $totalChapitres++;
                        if ($chapitre->estCompletePar($userId)) {
                            $chapitresCompletes++;
                            $moduleCompletes++;
                        }
                    }

                    $detailModules[] = [
                        'module_id' => $module->id,
                        'module_titre' => $module->titre,
                        'total_chapitres' => $moduleTotal,
                        'chapitres_completes' => $moduleCompletes,
                        'progres' => $moduleTotal > 0 ? round(($moduleCompletes / $moduleTotal) * 100, 2) : 0,
                    ];
                }

                return [
                    'formation_id' => $formation->id,
                    'formation_titre' => $formation->titre,
                    'formation_image' => $formation->image,
                    'domaine' => $formation->domaine ? $formation->domaine->name : 'N/A',
                    'progres_global' => $inscription->progres,
                    'total_chapitres' => $totalChapitres,
                    'chapitres_completes' => $chapitresCompletes,
                    'modules' => $detailModules,
                    'date_inscription' => $inscription->created_at,
                    'derniere_activite' => $inscription->updated_at,
                ];
            });

            // Statistiques globales
            $statsGlobales = [
                'total_formations' => $inscriptions->count(),
                'formations_en_cours' => $inscriptions->where('progres', '<', 100)->count(),
                'formations_terminees' => $inscriptions->where('progres', 100)->count(),
                'progression_moyenne' => round($inscriptions->avg('progres') ?? 0, 2),
                'total_chapitres_completes' => $progressionParFormation->sum('chapitres_completes'),
                'total_chapitres' => $progressionParFormation->sum('total_chapitres'),
            ];

            return response()->json([
                'success' => true,
                'statistiques' => $statsGlobales,
                'progression_par_formation' => $progressionParFormation,
            ]);
        } catch (\Exception $e) {
            \Log::error('Erreur progression: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement de la progression',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mes communautés
     */
    public function mesCommunautes(Request $request)
    {
        try {
            $userId = $request->user()->id;

            $communautes = Communaute::whereHas('membres', function($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->with([
                'formation.domaine',
                'formation.formateur',
                'membres'
            ])
            ->withCount('messages')
            ->get()
            ->map(function ($communaute) use ($userId) {
                $membre = $communaute->membres->firstWhere('id', $userId);
                
                return [
                    'id' => $communaute->id,
                    'nom' => $communaute->nom,
                    'description' => $communaute->description,
                    'formation' => [
                        'id' => $communaute->formation->id,
                        'titre' => $communaute->formation->titre,
                        'domaine' => $communaute->formation->domaine ? $communaute->formation->domaine->name : 'N/A',
                        'formateur' => $communaute->formation->formateur ? $communaute->formation->formateur->name : 'N/A',
                    ],
                    'total_membres' => $communaute->membres->count(),
                    'total_messages' => $communaute->messages_count,
                    'mon_role' => $membre && $membre->pivot ? $membre->pivot->role : 'membre',
                    'is_muted' => $membre && $membre->pivot ? $membre->pivot->is_muted : false,
                    'joined_at' => $membre && $membre->pivot ? $membre->pivot->joined_at : null,
                ];
            });

            return response()->json([
                'success' => true,
                'communautes' => $communautes,
            ]);
        } catch (\Exception $e) {
            \Log::error('Erreur communautés: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des communautés',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Formations terminées
     */
    public function formationsTerminees(Request $request)
    {
        try {
            $inscriptions = Inscription::where('inscriptions.user_id', $request->user()->id)
                ->whereIn('inscriptions.statut', ['active', 'approuvee'])
                ->where('inscriptions.progres', 100)
                ->with(['formation.domaine', 'formation.formateur'])
                ->latest('inscriptions.updated_at')
                ->get();

            return response()->json([
                'success' => true,
                'formations' => $inscriptions,
            ]);
        } catch (\Exception $e) {
            \Log::error('Erreur formations terminées: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Contenu d'une formation (après inscription)
     */
    public function contenuFormation(Request $request, Formation $formation)
    {
        try {
            // Vérifier l'inscription
            $inscription = Inscription::where('inscriptions.user_id', $request->user()->id)
                ->where('inscriptions.formation_id', $formation->id)
                ->whereIn('inscriptions.statut', ['active', 'approuvee'])
                ->where('inscriptions.is_blocked', false)
                ->first();

            if (!$inscription) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'avez pas accès à cette formation',
                ], 403);
            }

            // Charger toutes les relations nécessaires
            $formation->load([
                'domaine',
                'formateur',
                'modules.chapitres' => function ($query) {
                    $query->orderBy('ordre');
                },
                'communaute'
            ]);

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
        } catch (\Exception $e) {
            \Log::error('Erreur contenu formation: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lire un chapitre spécifique
     */
    public function lireChapitre(Request $request, Chapitre $chapitre)
    {
        try {
            // Charger les relations
            $chapitre->load(['module.formation', 'quiz.questions.options']);

            // Vérifier l'accès
            $formation = $chapitre->module->formation;
            $inscription = Inscription::where('inscriptions.user_id', $request->user()->id)
                ->where('inscriptions.formation_id', $formation->id)
                ->whereIn('inscriptions.statut', ['active', 'approuvee'])
                ->where('inscriptions.is_blocked', false)
                ->first();

            if (!$inscription) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'avez pas accès à ce contenu',
                ], 403);
            }

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
        } catch (\Exception $e) {
            \Log::error('Erreur lire chapitre: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Marquer un chapitre comme terminé
     */
    public function terminerChapitre(Request $request, Chapitre $chapitre)
    {
        try {
            // Vérifier que l'apprenant a accès à cette formation
            $formation = $chapitre->module->formation;
            $inscription = Inscription::where('inscriptions.user_id', $request->user()->id)
                ->where('inscriptions.formation_id', $formation->id)
                ->whereIn('inscriptions.statut', ['active', 'approuvee'])
                ->where('inscriptions.is_blocked', false)
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
        } catch (\Exception $e) {
            \Log::error('Erreur terminer chapitre: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la sauvegarde',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}