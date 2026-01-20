<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\Chapitre;
use App\Models\QuizQuestion;
use App\Models\QuizResultat;
use Illuminate\Http\Request;

class QuizController extends Controller
{
    /**
     * Créer un quiz pour un chapitre
     */
    public function store(Request $request, Chapitre $chapitre)
    {
        // Vérifier que c'est le formateur
        if ($chapitre->module->formation->formateur_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé',
            ], 403);
        }

        $request->validate([
            'titre' => 'required|string|max:255',
            'description' => 'nullable|string',
            'duree_minutes' => 'nullable|integer',
            'note_passage' => 'integer|min:0|max:100',
        ]);

        $quiz = $chapitre->quiz()->create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Quiz créé avec succès',
            'quiz' => $quiz,
        ], 201);
    }

    /**
     * Ajouter une question au quiz
     */
    public function addQuestion(Request $request, Quiz $quiz)
    {
        $request->validate([
            'question' => 'required|string',
            'type' => 'required|in:choix_multiple,vrai_faux',
            'points' => 'integer|min:1',
            'ordre' => 'nullable|integer',
            'options' => 'required|array|min:2',
            'options.*.option_texte' => 'required|string',
            'options.*.is_correct' => 'required|boolean',
        ]);

        $question = $quiz->questions()->create([
            'question' => $request->question,
            'type' => $request->type,
            'points' => $request->points ?? 1,
            'ordre' => $request->ordre ?? $quiz->questions()->count() + 1,
        ]);

        // Ajouter les options
        foreach ($request->options as $optionData) {
            $question->options()->create($optionData);
        }

        return response()->json([
            'success' => true,
            'message' => 'Question ajoutée',
            'question' => $question->load('options'),
        ], 201);
    }

    /**
     * Afficher un quiz avec ses questions
     */
    public function show(Quiz $quiz)
    {
        $quiz->load('questions.options', 'chapitre');

        return response()->json([
            'success' => true,
            'quiz' => $quiz,
        ]);
    }

    /**
     * Soumettre les réponses d'un quiz
     */
    public function soumettre(Request $request, Quiz $quiz)
    {
        $request->validate([
            'reponses' => 'required|array',
            'reponses.*.question_id' => 'required|exists:quiz_questions,id',
            'reponses.*.option_ids' => 'required|array',
            'temps_ecoule' => 'nullable|integer',
        ]);

        $score = 0;
        $scoreMax = $quiz->scoreMaximum();

        foreach ($request->reponses as $reponse) {
            $question = QuizQuestion::find($reponse['question_id']);
            
            if ($question && $question->verifierReponse($reponse['option_ids'])) {
                $score += $question->points;
            }
        }

        $pourcentage = ($scoreMax > 0) ? ($score / $scoreMax) * 100 : 0;
        $statut = $pourcentage >= $quiz->note_passage ? 'reussi' : 'echoue';

        $resultat = QuizResultat::create([
            'user_id' => $request->user()->id,
            'quiz_id' => $quiz->id,
            'score' => $score,
            'score_max' => $scoreMax,
            'pourcentage' => $pourcentage,
            'statut' => $statut,
            'temps_ecoule' => $request->temps_ecoule,
        ]);

        return response()->json([
            'success' => true,
            'message' => $statut === 'reussi' ? 'Quiz réussi !' : 'Quiz échoué',
            'resultat' => $resultat,
        ]);
    }

    /**
     * Obtenir les résultats d'un utilisateur pour un quiz
     */
    public function mesResultats(Request $request, Quiz $quiz)
    {
        $resultats = $quiz->resultats()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'resultats' => $resultats,
        ]);
    }

    /**
     * Mettre à jour un quiz
     */
    public function update(Request $request, Quiz $quiz)
    {
        // Vérifier que c'est le formateur
        if ($quiz->chapitre->module->formation->formateur_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé',
            ], 403);
        }

        $request->validate([
            'titre' => 'string|max:255',
            'description' => 'nullable|string',
            'duree_minutes' => 'nullable|integer',
            'note_passage' => 'integer|min:0|max:100',
        ]);

        $quiz->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Quiz mis à jour',
            'quiz' => $quiz,
        ]);
    }

    /**
     * Supprimer un quiz
     */
    public function destroy(Request $request, Quiz $quiz)
    {
        if ($quiz->chapitre->module->formation->formateur_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé',
            ], 403);
        }

        $quiz->delete();

        return response()->json([
            'success' => true,
            'message' => 'Quiz supprimé',
        ]);
    }
}