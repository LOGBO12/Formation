<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Domaine;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DomaineController extends Controller
{
    /**
     * Liste tous les domaines actifs (public)
     */
    public function index()
    {
        $domaines = Domaine::where('is_active', true)->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'domaines' => $domaines,
        ]);
    }

    /**
     * Liste TOUS les domaines (Super Admin seulement)
     */
    public function adminIndex(Request $request)
    {
        if (!$request->user()->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé',
            ], 403);
        }

        $domaines = Domaine::orderBy('name')->get();

        return response()->json([
            'success' => true,
            'domaines' => $domaines,
        ]);
    }

    /**
     * Créer un nouveau domaine (Super Admin)
     */
    public function store(Request $request)
    {
        if (!$request->user()->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé',
            ], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255|unique:domaines',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:50',
        ]);

        $domaine = Domaine::create([
            'name' => $request->name,
            //'slug' => Str::slug($request->name),
            'description' => $request->description,
            'icon' => $request->icon ?? 'folder',
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Domaine créé avec succès',
            'domaine' => $domaine,
        ], 201);
    }

    /**
     * Mettre à jour un domaine (Super Admin)
     */
    public function update(Request $request, Domaine $domaine)
    {
        if (!$request->user()->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé',
            ], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255|unique:domaines,name,' . $domaine->id,
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:50',
            'is_active' => 'boolean',
        ]);

        $domaine->update([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'description' => $request->description,
            'icon' => $request->icon,
            'is_active' => $request->is_active ?? $domaine->is_active,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Domaine mis à jour avec succès',
            'domaine' => $domaine,
        ]);
    }

    /**
     * Supprimer un domaine (Super Admin)
     */
    public function destroy(Request $request, Domaine $domaine)
    {
        if (!$request->user()->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé',
            ], 403);
        }

        $domaine->delete();

        return response()->json([
            'success' => true,
            'message' => 'Domaine supprimé avec succès',
        ]);
    }

    /**
     * Activer/Désactiver un domaine (Super Admin)
     */
    public function toggleStatus(Request $request, Domaine $domaine)
    {
        if (!$request->user()->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé',
            ], 403);
        }

        $domaine->update([
            'is_active' => !$domaine->is_active,
        ]);

        return response()->json([
            'success' => true,
            'message' => $domaine->is_active ? 'Domaine activé' : 'Domaine désactivé',
            'domaine' => $domaine,
        ]);
    }
}