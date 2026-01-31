<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Formation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PublicFormationController extends Controller
{
    /**
     * Liste publique des formations (sans authentification)
     */
    public function index(Request $request)
    {
        try {
            $query = Formation::where('statut', 'publie')
                ->with(['domaine', 'formateur:id,name'])
                ->withCount('inscriptions');

            // Filtres
            if ($request->has('domaine_id') && $request->domaine_id != '') {
                $query->where('domaine_id', $request->domaine_id);
            }

            if ($request->has('is_free') && $request->is_free != '') {
                $query->where('is_free', $request->is_free);
            }

            if ($request->has('search') && $request->search != '') {
                $query->where(function($q) use ($request) {
                    $q->where('titre', 'like', '%' . $request->search . '%')
                      ->orWhere('description', 'like', '%' . $request->search . '%');
                });
            }

            // Tri
            $sortBy = $request->get('sort_by', 'recent');
            switch ($sortBy) {
                case 'popular':
                    $query->orderBy('inscriptions_count', 'desc');
                    break;
                case 'price_asc':
                    $query->orderBy('prix', 'asc');
                    break;
                case 'price_desc':
                    $query->orderBy('prix', 'desc');
                    break;
                default:
                    $query->latest('created_at');
            }

            $formations = $query->paginate(12);

            Log::info('📚 Formations publiques chargées', [
                'total' => $formations->total(),
            ]);

            return response()->json([
                'success' => true,
                'formations' => $formations,
            ], 200);

        } catch (\Exception $e) {
            Log::error('❌ Erreur PublicFormationController@index', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des formations',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Détails d'une formation publique (par lien public)
     */
    public function show($lienPublic)
    {
        try {
            $formation = Formation::where('lien_public', $lienPublic)
                ->where('statut', 'publie')
                ->with([
                    'domaine',
                    'formateur:id,name',
                    'modules' => function($query) {
                        $query->orderBy('ordre');
                    },
                    'modules.chapitres' => function($query) {
                        $query->orderBy('ordre')
                              ->select('id', 'module_id', 'titre', 'type', 'duree', 'ordre', 'is_preview');
                    }
                ])
                ->withCount('inscriptions')
                ->firstOrFail();

            // Calculer la durée totale et le nombre de chapitres
            $formation->total_chapitres = 0;
            $formation->duree_totale = 0;

            foreach ($formation->modules as $module) {
                $formation->total_chapitres += $module->chapitres->count();
                $formation->duree_totale += $module->chapitres->sum('duree');
            }

            Log::info('📖 Formation publique consultée', [
                'lien_public' => $lienPublic,
                'titre' => $formation->titre,
            ]);

            return response()->json([
                'success' => true,
                'formation' => $formation,
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Formation non trouvée',
            ], 404);

        } catch (\Exception $e) {
            Log::error('❌ Erreur PublicFormationController@show', [
                'lien_public' => $lienPublic,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Statistiques publiques
     */
    public function stats()
    {
        try {
            $stats = [
                'total_formations' => Formation::where('statut', 'publie')->count(),
                'total_gratuit' => Formation::where('statut', 'publie')->where('is_free', true)->count(),
                'total_apprenants' => \App\Models\Inscription::whereIn('statut', ['active', 'approuvee'])->distinct('user_id')->count(),
                'total_formateurs' => \App\Models\User::where('role', 'formateur')->count(),
            ];

            return response()->json([
                'success' => true,
                'stats' => $stats,
            ], 200);

        } catch (\Exception $e) {
            Log::error('❌ Erreur stats publiques', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des statistiques',
            ], 500);
        }
    }
}