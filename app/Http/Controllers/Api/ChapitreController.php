<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chapitre;
use App\Models\Module;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ChapitreController extends Controller
{
    /**
     * Liste des chapitres d'un module
     */
    public function index(Module $module)
    {
        $chapitres = $module->chapitres()->with('quiz')->get();

        return response()->json([
            'success' => true,
            'chapitres' => $chapitres,
        ]);
    }

    /**
     * Créer un chapitre
     */
    public function store(Request $request, Module $module)
    {
        // Vérifier que c'est le formateur
        if ($module->formation->formateur_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé',
            ], 403);
        }

        $request->validate([
            'titre' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:video,pdf,texte,quiz',
            'contenu' => 'nullable|string',
            'duree' => 'nullable|integer',
            'ordre' => 'nullable|integer',
            'is_preview' => 'boolean',
            'fichier' => 'nullable|file|max:51200', // 50MB max
        ]);

        $data = $request->except('fichier');
        $data['ordre'] = $request->ordre ?? $module->chapitres()->count() + 1;

        // Upload de fichier (vidéo ou PDF)
        if ($request->hasFile('fichier')) {
            $data['contenu'] = $request->file('fichier')->store('chapitres', 'public');
        }

        $chapitre = $module->chapitres()->create($data);

        return response()->json([
            'success' => true,
            'message' => 'Chapitre créé avec succès',
            'chapitre' => $chapitre,
        ], 201);
    }

    /**
     * Afficher un chapitre
     */
    public function show(Chapitre $chapitre)
    {
        $chapitre->load('quiz.questions.options');

        return response()->json([
            'success' => true,
            'chapitre' => $chapitre,
        ]);
    }

    /**
     * Mettre à jour un chapitre
     */
    public function update(Request $request, Chapitre $chapitre)
    {
        // Vérifier que c'est le formateur
        if ($chapitre->module->formation->formateur_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé',
            ], 403);
        }

        $request->validate([
            'titre' => 'string|max:255',
            'description' => 'nullable|string',
            'type' => 'in:video,pdf,texte,quiz',
            'contenu' => 'nullable|string',
            'duree' => 'nullable|integer',
            'ordre' => 'nullable|integer',
            'is_preview' => 'boolean',
            'fichier' => 'nullable|file|max:51200',
        ]);

        $data = $request->except('fichier');

        // Upload de nouveau fichier
        if ($request->hasFile('fichier')) {
            // Supprimer l'ancien fichier
            if ($chapitre->contenu && in_array($chapitre->type, ['video', 'pdf'])) {
                Storage::disk('public')->delete($chapitre->contenu);
            }
            $data['contenu'] = $request->file('fichier')->store('chapitres', 'public');
        }

        $chapitre->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Chapitre mis à jour',
            'chapitre' => $chapitre,
        ]);
    }

    /**
     * Supprimer un chapitre
     */
    public function destroy(Request $request, Chapitre $chapitre)
    {
        if ($chapitre->module->formation->formateur_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé',
            ], 403);
        }

        // Supprimer le fichier
        if ($chapitre->contenu && in_array($chapitre->type, ['video', 'pdf'])) {
            Storage::disk('public')->delete($chapitre->contenu);
        }

        $chapitre->delete();

        return response()->json([
            'success' => true,
            'message' => 'Chapitre supprimé',
        ]);
    }

    /**
     * Marquer un chapitre comme complété
     */
    public function marquerComplete(Request $request, Chapitre $chapitre)
    {
        $progression = $chapitre->progressions()->updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'is_completed' => true,
                'date_completion' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Chapitre marqué comme complété',
            'progression' => $progression,
        ]);
    }
}