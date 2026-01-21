<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OnboardingController;
use App\Http\Controllers\Api\DomaineController;
use App\Http\Controllers\Api\AdminController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/
// Gestion des utilisateurs (Super Admin)
Route::middleware(['auth:sanctum', 'check.profile'])->prefix('admin')->group(function () {
    Route::get('/users', [AdminController::class, 'users']);
    Route::patch('/users/{user}/toggle-status', [AdminController::class, 'toggleUserStatus']);
    Route::get('/formations', [AdminController::class, 'allFormations']);
});

// Routes publiques (pas d'authentification requise)
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

// Domaines publics (pour affichage lors de l'onboarding)
Route::get('/domaines', [DomaineController::class, 'index']);

// Formation publique par lien
Route::get('/formations/lien/{lienPublic}', [\App\Http\Controllers\Api\FormationController::class, 'showByLink']);

// Routes protégées (authentification requise)
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Onboarding (accessible même si profil non complété)
    Route::prefix('onboarding')->group(function () {
        Route::post('/select-role', [OnboardingController::class, 'selectRole']);
        Route::post('/complete-profile', [OnboardingController::class, 'completeProfile']);
        Route::post('/skip-profile', [OnboardingController::class, 'skipProfile']);
        Route::post('/accept-privacy', [OnboardingController::class, 'acceptPrivacyPolicy']);
    });

    // Routes nécessitant un profil complété
    Route::middleware('check.profile')->group(function () {
        
        // Super Admin - Gestion des domaines
        Route::prefix('admin')->group(function () {
            Route::get('/domaines', [DomaineController::class, 'adminIndex']);
            Route::post('/domaines', [DomaineController::class, 'store']);
            Route::put('/domaines/{domaine}', [DomaineController::class, 'update']);
            Route::delete('/domaines/{domaine}', [DomaineController::class, 'destroy']);
            Route::patch('/domaines/{domaine}/toggle', [DomaineController::class, 'toggleStatus']);
        });

        // Formations (Formateur)
        Route::prefix('formations')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\FormationController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Api\FormationController::class, 'store']);
            Route::get('/{formation}', [\App\Http\Controllers\Api\FormationController::class, 'show']);
            Route::put('/{formation}', [\App\Http\Controllers\Api\FormationController::class, 'update']);
            Route::delete('/{formation}', [\App\Http\Controllers\Api\FormationController::class, 'destroy']);
            Route::patch('/{formation}/statut', [\App\Http\Controllers\Api\FormationController::class, 'changeStatut']);
            Route::get('/{formation}/statistiques', [\App\Http\Controllers\Api\FormationController::class, 'statistiques']);
            
            // Modules
            Route::get('/{formation}/modules', [\App\Http\Controllers\Api\ModuleController::class, 'index']);
            Route::post('/{formation}/modules', [\App\Http\Controllers\Api\ModuleController::class, 'store']);
        });

        // Modules
        Route::prefix('modules')->group(function () {
            Route::get('/{module}', [\App\Http\Controllers\Api\ModuleController::class, 'show']);
            Route::put('/{module}', [\App\Http\Controllers\Api\ModuleController::class, 'update']);
            Route::delete('/{module}', [\App\Http\Controllers\Api\ModuleController::class, 'destroy']);
            
            // Chapitres
            Route::get('/{module}/chapitres', [\App\Http\Controllers\Api\ChapitreController::class, 'index']);
            Route::post('/{module}/chapitres', [\App\Http\Controllers\Api\ChapitreController::class, 'store']);
        });

        // Chapitres
        Route::prefix('chapitres')->group(function () {
            Route::get('/{chapitre}', [\App\Http\Controllers\Api\ChapitreController::class, 'show']);
            Route::post('/{chapitre}', [\App\Http\Controllers\Api\ChapitreController::class, 'update']);
            Route::delete('/{chapitre}', [\App\Http\Controllers\Api\ChapitreController::class, 'destroy']);
            Route::post('/{chapitre}/complete', [\App\Http\Controllers\Api\ChapitreController::class, 'marquerComplete']);
            
            // Quiz
            Route::post('/{chapitre}/quiz', [\App\Http\Controllers\Api\QuizController::class, 'store']);
        });

        // Quiz
        Route::prefix('quiz')->group(function () {
            Route::get('/{quiz}', [\App\Http\Controllers\Api\QuizController::class, 'show']);
            Route::put('/{quiz}', [\App\Http\Controllers\Api\QuizController::class, 'update']);
            Route::delete('/{quiz}', [\App\Http\Controllers\Api\QuizController::class, 'destroy']);
            Route::post('/{quiz}/questions', [\App\Http\Controllers\Api\QuizController::class, 'addQuestion']);
            Route::post('/{quiz}/soumettre', [\App\Http\Controllers\Api\QuizController::class, 'soumettre']);
            Route::get('/{quiz}/mes-resultats', [\App\Http\Controllers\Api\QuizController::class, 'mesResultats']);
        });

        // Inscriptions
        Route::prefix('inscriptions')->group(function () {
            Route::post('/formations/{formation}/demander', [\App\Http\Controllers\Api\InscriptionController::class, 'demander']);
            Route::post('/{inscription}/approuver', [\App\Http\Controllers\Api\InscriptionController::class, 'approuver']);
            Route::post('/{inscription}/rejeter', [\App\Http\Controllers\Api\InscriptionController::class, 'rejeter']);
            Route::post('/{inscription}/bloquer', [\App\Http\Controllers\Api\InscriptionController::class, 'bloquer']);
            Route::post('/{inscription}/debloquer', [\App\Http\Controllers\Api\InscriptionController::class, 'debloquer']);
            Route::get('/{inscription}/progression', [\App\Http\Controllers\Api\InscriptionController::class, 'progression']);
        });

        // Apprenants d'une formation
        Route::get('/formations/{formation}/apprenants', [\App\Http\Controllers\Api\InscriptionController::class, 'apprenants']);

        // Communautés
        Route::prefix('communautes')->group(function () {
            Route::get('/{communaute}', [\App\Http\Controllers\Api\CommunauteController::class, 'show']);
            Route::get('/{communaute}/messages', [\App\Http\Controllers\Api\CommunauteController::class, 'messages']);
            Route::post('/{communaute}/messages', [\App\Http\Controllers\Api\CommunauteController::class, 'envoyerMessage']);
            Route::post('/{communaute}/annonces', [\App\Http\Controllers\Api\CommunauteController::class, 'envoyerAnnonce']);
            Route::get('/{communaute}/membres', [\App\Http\Controllers\Api\CommunauteController::class, 'membres']);
            Route::post('/{communaute}/membres/{userId}/muter', [\App\Http\Controllers\Api\CommunauteController::class, 'muterMembre']);
            Route::post('/{communaute}/membres/{userId}/demuter', [\App\Http\Controllers\Api\CommunauteController::class, 'demuterMembre']);
        });

        // Messages communauté
        Route::prefix('messages')->group(function () {
            Route::post('/{message}/epingler', [\App\Http\Controllers\Api\CommunauteController::class, 'epinglerMessage']);
            Route::post('/{message}/desepingler', [\App\Http\Controllers\Api\CommunauteController::class, 'desepinglerMessage']);
            Route::delete('/{message}', [\App\Http\Controllers\Api\CommunauteController::class, 'supprimerMessage']);
        });

        // Statistiques Formateur
        Route::prefix('statistiques')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\StatistiquesController::class, 'index']);
            Route::get('/revenus', [\App\Http\Controllers\Api\StatistiquesController::class, 'revenus']);
            Route::get('/apprenants', [\App\Http\Controllers\Api\StatistiquesController::class, 'apprenants']);
            Route::get('/demandes-en-attente', [\App\Http\Controllers\Api\StatistiquesController::class, 'demandesEnAttente']);
            Route::get('/graphique-inscriptions', [\App\Http\Controllers\Api\StatistiquesController::class, 'graphiqueInscriptions']);
        });
    });
});