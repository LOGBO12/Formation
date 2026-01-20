<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Formation;
use Illuminate\Http\Request;

class ModuleController extends Controller
{
    /**
     * Liste des modules d'une formation
     */
    public function index(Formation $formation)
    {
        $modules = $formation->modules()->with('chapitres')->get();

        return response()->json([
            'success' => true,
            'modules' => $modules,
        ]);
    }

    /**
     * Créer un module
     */
    public function store(Request $request, Formation $formation)
    {
        // Vérifier que c'est le formateur
        if ($formation->formateur_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé',
            ], 403);
        }

        $request->validate([
            'titre' => 'required|string|max:255',
            'description' => 'nullable|string',
            'ordre' => 'nullable|integer',
        ]);

        $module = $formation->modules()->create([
            'titre' => $request->titre,
            'description' => $request->description,
            'ordre' => $request->ordre ?? $formation->modules()->count() + 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Module créé avec succès',
            'module' => $module,
        ], 201);
    }

    /**
     * Afficher un module
     */
    public function show(Module $module)
    {
        $module->load('chapitres');

        return response()->json([
            'success' => true,
            'module' => $module,
        ]);
    }

    /**
     * Mettre à jour un module
     */
    public function update(Request $request, Module $module)
    {
        // Vérifier que c'est le formateur
        if ($module->formation->formateur_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé',
            ], 403);
        }

        $request->validate([
            'titre' => 'string|max:255',
            'description' => 'nullable|string',
            'ordre' => 'nullable|integer',
        ]);

        $module->update($request->only(['titre', 'description', 'ordre']));

        return response()->json([
            'success' => true,
            'message' => 'Module mis à jour',
            'module' => $module,
        ]);
    }

    /**
     * Supprimer un module
     */
    public function destroy(Request $request, Module $module)
    {
        if ($module->formation->formateur_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé',
            ], 403);
        }

        $module->delete();

        return response()->json([
            'success' => true,
            'message' => 'Module supprimé',
        ]);
    }
}