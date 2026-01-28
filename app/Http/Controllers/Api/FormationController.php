<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Formation;
use App\Models\Communaute;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class FormationController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Liste des formations du formateur connecté
     */
    public function index(Request $request)
    {
        $formations = Formation::where('formateur_id', $request->user()->id)
            ->with(['domaine', 'modules.chapitres'])
            ->withCount('inscriptions')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'formations' => $formations,
        ]);
    }

    /**
     * Créer une nouvelle formation
     */
    public function store(Request $request)
    {
        $request->validate([
            'titre' => 'required|string|max:255',
            'description' => 'required|string',
            'domaine_id' => 'required|exists:domaines,id',
            'prix' => 'nullable|numeric|min:0',
            'is_free' => 'boolean',
            'duree_estimee' => 'nullable|integer|min:1',
            'image' => 'nullable|image|max:2048',
        ]);

        $data = $request->all();
        $data['formateur_id'] = $request->user()->id;
        $data['statut'] = 'brouillon'; // ✅ Toujours en brouillon à la création
        
        // Générer un slug unique
        $baseSlug = Str::slug($request->titre);
        $slug = $baseSlug;
        $count = 1;
        
        while (Formation::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $count;
            $count++;
        }
        
        $data['slug'] = $slug;
        $data['lien_public'] = Str::random(10);

        // Upload de l'image
        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('formations', 'public');
        }

        // Gérer is_free
        if (isset($data['is_free'])) {
            $data['is_free'] = filter_var($data['is_free'], FILTER_VALIDATE_BOOLEAN);
        }

        $formation = Formation::create($data);

        Log::info('✅ Formation créée en brouillon', [
            'formation_id' => $formation->id,
            'titre' => $formation->titre,
            'statut' => $formation->statut,
        ]);

        // ❌ PAS de notification ici car en brouillon

        return response()->json([
            'success' => true,
            'message' => 'Formation créée avec succès',
            'formation' => $formation->load('domaine'),
        ], 201);
    }

    /**
     * Afficher une formation
     */
    public function show(Formation $formation)
    {
        $formation->load([
            'domaine',
            'formateur',
            'modules.chapitres',
            'communaute.membres',
        ]);

        $formation->total_apprenants = $formation->totalApprenants();
        $formation->total_revenus = $formation->totalRevenus();

        return response()->json([
            'success' => true,
            'formation' => $formation,
        ]);
    }

    /**
     * Mettre à jour une formation
     */
    public function update(Request $request, Formation $formation)
    {
        // Vérifier que c'est bien le formateur
        if ($formation->formateur_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé',
            ], 403);
        }

        $request->validate([
            'titre' => 'string|max:255',
            'description' => 'string',
            'domaine_id' => 'exists:domaines,id',
            'prix' => 'nullable|numeric|min:0',
            'is_free' => 'boolean',
            'duree_estimee' => 'nullable|integer|min:1',
            'image' => 'nullable|image|max:2048',
        ]);

        $data = $request->except(['formateur_id', 'slug', 'lien_public']);

        if ($request->has('titre')) {
            $data['slug'] = Str::slug($request->titre);
        }

        // Upload de l'image
        if ($request->hasFile('image')) {
            // Supprimer l'ancienne image
            if ($formation->image) {
                Storage::disk('public')->delete($formation->image);
            }
            $data['image'] = $request->file('image')->store('formations', 'public');
        }

        $formation->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Formation mise à jour',
            'formation' => $formation->load('domaine'),
        ]);
    }

    /**
     * Supprimer une formation
     */
    public function destroy(Request $request, Formation $formation)
    {
        if ($formation->formateur_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé',
            ], 403);
        }

        // Supprimer l'image
        if ($formation->image) {
            Storage::disk('public')->delete($formation->image);
        }

        $formation->delete();

        return response()->json([
            'success' => true,
            'message' => 'Formation supprimée',
        ]);
    }

    /**
     * ✅ CORRECTION: Changer le statut d'une formation
     * Envoie les notifications UNIQUEMENT lors de la publication
     */
    public function changeStatut(Request $request, Formation $formation)
    {
        if ($formation->formateur_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé',
            ], 403);
        }

        $request->validate([
            'statut' => 'required|in:brouillon,publie,archive',
        ]);

        $ancienStatut = $formation->statut;
        $nouveauStatut = $request->statut;

        $formation->update(['statut' => $nouveauStatut]);

        Log::info('📝 Changement de statut formation', [
            'formation_id' => $formation->id,
            'ancien_statut' => $ancienStatut,
            'nouveau_statut' => $nouveauStatut,
        ]);

        // ✅ Envoyer les notifications UNIQUEMENT si passage de brouillon/archive à publié
        if ($nouveauStatut === 'publie' && in_array($ancienStatut, ['brouillon', 'archive'])) {
            Log::info('🔔 Envoi des notifications - Formation publiée', [
                'formation_id' => $formation->id,
            ]);

            $this->notificationService->notifierNouvelleFormation($formation);

            return response()->json([
                'success' => true,
                'message' => 'Formation publiée avec succès ! Les apprenants ont été notifiés.',
                'formation' => $formation,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Statut mis à jour',
            'formation' => $formation,
        ]);
    }

    /**
     * Afficher une formation par son lien public
     */
    public function showByLink($lienPublic)
    {
        $formation = Formation::where('lien_public', $lienPublic)
            ->where('statut', 'publie')
            ->with(['domaine', 'formateur', 'modules.chapitres'])
            ->firstOrFail();

        $formation->total_apprenants = $formation->totalApprenants();

        return response()->json([
            'success' => true,
            'formation' => $formation,
        ]);
    }

    /**
     * Statistiques d'une formation
     */
    public function statistiques(Request $request, Formation $formation)
    {
        if ($formation->formateur_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé',
            ], 403);
        }

        $stats = [
            'total_apprenants' => $formation->totalApprenants(),
            'total_revenus' => $formation->totalRevenus(),
            'inscriptions_en_attente' => $formation->inscriptions()->enAttente()->count(),
            'inscriptions_actives' => $formation->inscriptions()->actives()->count(),
            'inscriptions_bloquees' => $formation->inscriptions()->bloquees()->count(),
            'progression_moyenne' => $formation->inscriptions()->actives()->avg('progres') ?? 0,
        ];

        return response()->json([
            'success' => true,
            'statistiques' => $stats,
        ]);
    }

    /**
     * ✅ MÉTHODE OBSOLÈTE - Utiliser changeStatut à la place
     * Publie une formation (pour compatibilité)
     */
    public function publier(Formation $formation)
    {
        if ($formation->formateur_id !== auth()->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé',
            ], 403);
        }

        $ancienStatut = $formation->statut;
        $formation->update(['statut' => 'publie']);

        // ✅ Notifier UNIQUEMENT si c'était en brouillon avant
        if (in_array($ancienStatut, ['brouillon', 'archive'])) {
            $this->notificationService->notifierNouvelleFormation($formation);
            
            Log::info('🔔 Formation publiée et notifications envoyées', [
                'formation_id' => $formation->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Formation publiée avec succès ! Les apprenants ont été notifiés.',
            ], 200);
        }

        return response()->json([
            'success' => true,
            'message' => 'Formation publiée avec succès',
        ], 200);
    }
}