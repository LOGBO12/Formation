<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inscription;
use App\Models\Formation;
use App\Models\Communaute;
use Illuminate\Http\Request;

class InscriptionController extends Controller
{
    /**
     * Demander l'accès à une formation
     */
    public function demander(Request $request, Formation $formation)
    {
        // Vérifier que la formation est publiée
        if (!$formation->isPubliee()) {
            return response()->json([
                'success' => false,
                'message' => 'Cette formation n\'est pas disponible',
            ], 400);
        }

        // Vérifier si l'utilisateur n'est pas déjà inscrit
        $inscriptionExistante = Inscription::where('user_id', $request->user()->id)
            ->where('formation_id', $formation->id)
            ->first();

        if ($inscriptionExistante) {
            return response()->json([
                'success' => false,
                'message' => 'Vous êtes déjà inscrit à cette formation',
            ], 400);
        }

        // Si la formation est gratuite, accès immédiat
        if ($formation->is_free) {
            $inscription = Inscription::create([
                'user_id' => $request->user()->id,
                'formation_id' => $formation->id,
                'statut' => 'active',
                'date_approbation' => now(),
            ]);

            // Créer ou rejoindre la communauté
            $this->ajouterACommunaute($formation, $request->user()->id);

            return response()->json([
                'success' => true,
                'message' => 'Inscription réussie !',
                'inscription' => $inscription,
            ], 201);
        }

        // Si payante, demande en attente
        $inscription = Inscription::create([
            'user_id' => $request->user()->id,
            'formation_id' => $formation->id,
            'statut' => 'en_attente',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Demande d\'accès envoyée. En attente d\'approbation.',
            'inscription' => $inscription,
        ], 201);
    }

    /**
     * Approuver une demande d'inscription (Formateur)
     */
    public function approuver(Request $request, Inscription $inscription)
    {
        // Vérifier que c'est le formateur
        if ($inscription->formation->formateur_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé',
            ], 403);
        }

        $inscription->update([
            'statut' => 'active',
            'date_approbation' => now(),
        ]);

        // Ajouter à la communauté
        $this->ajouterACommunaute($inscription->formation, $inscription->user_id);

        return response()->json([
            'success' => true,
            'message' => 'Inscription approuvée',
            'inscription' => $inscription,
        ]);
    }

    /**
     * Rejeter une demande d'inscription (Formateur)
     */
    public function rejeter(Request $request, Inscription $inscription)
    {
        if ($inscription->formation->formateur_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé',
            ], 403);
        }

        $inscription->update(['statut' => 'rejetee']);

        return response()->json([
            'success' => true,
            'message' => 'Inscription rejetée',
        ]);
    }

    /**
     * Bloquer un apprenant (Formateur)
     */
    public function bloquer(Request $request, Inscription $inscription)
    {
        if ($inscription->formation->formateur_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé',
            ], 403);
        }

        $inscription->update([
            'is_blocked' => true,
            'statut' => 'bloquee',
        ]);

        // Muter dans la communauté
        if ($inscription->formation->communaute) {
            $inscription->formation->communaute->muterMembre($inscription->user_id);
        }

        return response()->json([
            'success' => true,
            'message' => 'Apprenant bloqué',
        ]);
    }

    /**
     * Débloquer un apprenant (Formateur)
     */
    public function debloquer(Request $request, Inscription $inscription)
    {
        if ($inscription->formation->formateur_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé',
            ], 403);
        }

        $inscription->update([
            'is_blocked' => false,
            'statut' => 'active',
        ]);

        // Démuter dans la communauté
        if ($inscription->formation->communaute) {
            $inscription->formation->communaute->demuterMembre($inscription->user_id);
        }

        return response()->json([
            'success' => true,
            'message' => 'Apprenant débloqué',
        ]);
    }

    /**
     * Liste des apprenants d'une formation (Formateur)
     */
    public function apprenants(Request $request, Formation $formation)
    {
        if ($formation->formateur_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé',
            ], 403);
        }

        $inscriptions = $formation->inscriptions()
            ->with('user')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'inscriptions' => $inscriptions,
        ]);
    }

    /**
     * Progression d'un apprenant
     */
    public function progression(Request $request, Inscription $inscription)
    {
        if ($inscription->formation->formateur_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé',
            ], 403);
        }

        $progres = $inscription->calculerProgression();

        return response()->json([
            'success' => true,
            'progres' => $progres,
            'inscription' => $inscription->fresh(),
        ]);
    }

    /**
     * Méthode privée pour ajouter à la communauté
     */
    private function ajouterACommunaute($formation, $userId)
    {
        // Créer la communauté si elle n'existe pas
        if (!$formation->communaute) {
            $communaute = Communaute::create([
                'formation_id' => $formation->id,
                'nom' => 'Communauté - ' . $formation->titre,
                'description' => 'Communauté des apprenants de ' . $formation->titre,
            ]);

            // Ajouter le formateur comme admin
            $communaute->ajouterMembre($formation->formateur_id, 'admin');
        } else {
            $communaute = $formation->communaute;
        }

        // Ajouter l'apprenant
        $communaute->ajouterMembre($userId, 'membre');
    }
}