<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OnboardingController;
use App\Http\Controllers\Api\DomaineController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\ApprenantController;
use App\Http\Controllers\Api\FormationController;
use App\Http\Controllers\Api\ModuleController;
use App\Http\Controllers\Api\ChapitreController;
use App\Http\Controllers\Api\QuizController;
use App\Http\Controllers\Api\InscriptionController;
use App\Http\Controllers\Api\CommunauteController;
use App\Http\Controllers\Api\StatistiquesController;
use App\Http\Controllers\Api\FormateurController;
use App\Http\Controllers\Api\PaiementController;
use App\Http\Controllers\Api\FormateurPaymentController; // ⚠️ AJOUT MANQUANT

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// ==========================================
// ROUTES PUBLIQUES (pas d'authentification)
// ==========================================

// Callbacks FedaPay (DOIVENT ÊTRE AVANT auth:sanctum)
Route::get('/fedapay/callback', [PaiementController::class, 'callback'])->name('fedapay.callback');
Route::post('/fedapay/webhook', [PaiementController::class, 'webhook'])->name('fedapay.webhook');

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

// Domaines publics (pour affichage lors de l'onboarding)
Route::get('/domaines', [DomaineController::class, 'index']);

// Formation publique par lien
Route::get('/formations/lien/{lienPublic}', [FormationController::class, 'showByLink']);

// ==========================================
// ROUTES PROTÉGÉES (authentification requise)
// ==========================================

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
        
        // ==========================================
        // ROUTES APPRENANT
        // ==========================================
        Route::prefix('apprenant')->group(function () {
            // Dashboard
            Route::get('/dashboard', [ApprenantController::class, 'dashboard']);
            
            // Mes formations
            Route::get('/mes-formations', [ApprenantController::class, 'mesFormations']);
            Route::get('/formations-terminees', [ApprenantController::class, 'formationsTerminees']);
            
            // Catalogue
            Route::get('/catalogue', [ApprenantController::class, 'catalogue']);
            
            // Progression
            Route::get('/progression', [ApprenantController::class, 'progression']);
            
            // Communautés
            Route::get('/communautes', [ApprenantController::class, 'mesCommunautes']);
            
            // Contenu formation
            Route::get('/formations/{formation}/contenu', [ApprenantController::class, 'contenuFormation']);
            Route::get('/chapitres/{chapitre}', [ApprenantController::class, 'lireChapitre']);
            Route::post('/chapitres/{chapitre}/terminer', [ApprenantController::class, 'terminerChapitre']);
        });

        // ==========================================
        // ROUTES PAIEMENTS (Apprenant)
        // ==========================================
        Route::prefix('paiements')->group(function () {
            Route::post('/formations/{formation}/initier', [PaiementController::class, 'initierPaiement']);
            Route::get('/{paiement}/statut', [PaiementController::class, 'verifierStatut']);
            Route::get('/mes-paiements', [PaiementController::class, 'mesPaiements']);
        });

        // ==========================================
        // ROUTES FORMATEUR
        // ==========================================
        Route::prefix('formateur')->group(function () {
            // Communautés
            Route::get('/mes-communautes', [FormateurController::class, 'mesCommunautes']);
            
            // 💰 Paiements et Revenus
            Route::get('/paiements-recus', [FormateurPaymentController::class, 'paiementsRecus']);
            Route::get('/payment-settings', [FormateurPaymentController::class, 'getPaymentSettings']);
            Route::post('/payment-settings/update', [FormateurPaymentController::class, 'updatePaymentSettings']);
        });

        // ==========================================
        // ROUTES SUPER ADMIN
        // ==========================================
        Route::prefix('admin')->group(function () {
            // Gestion des domaines
            Route::get('/domaines', [DomaineController::class, 'adminIndex']);
            Route::post('/domaines', [DomaineController::class, 'store']);
            Route::put('/domaines/{domaine}', [DomaineController::class, 'update']);
            Route::delete('/domaines/{domaine}', [DomaineController::class, 'destroy']);
            Route::patch('/domaines/{domaine}/toggle', [DomaineController::class, 'toggleStatus']);
            
            // Gestion des utilisateurs
            Route::get('/users', [AdminController::class, 'users']);
            Route::patch('/users/{user}/toggle-status', [AdminController::class, 'toggleUserStatus']);
            
            // Gestion des formations
            Route::get('/formations', [AdminController::class, 'allFormations']);
        });

        // ==========================================
        // ROUTES FORMATIONS (Formateur)
        // ==========================================
        Route::prefix('formations')->group(function () {
            Route::get('/', [FormationController::class, 'index']);
            Route::post('/', [FormationController::class, 'store']);
            Route::get('/{formation}', [FormationController::class, 'show']);
            Route::put('/{formation}', [FormationController::class, 'update']);
            Route::delete('/{formation}', [FormationController::class, 'destroy']);
            Route::patch('/{formation}/statut', [FormationController::class, 'changeStatut']);
            Route::get('/{formation}/statistiques', [FormationController::class, 'statistiques']);
            
            // Modules
            Route::get('/{formation}/modules', [ModuleController::class, 'index']);
            Route::post('/{formation}/modules', [ModuleController::class, 'store']);
            
            // Apprenants d'une formation
            Route::get('/{formation}/apprenants', [InscriptionController::class, 'apprenants']);
        });

        // Modules
        Route::prefix('modules')->group(function () {
            Route::get('/{module}', [ModuleController::class, 'show']);
            Route::put('/{module}', [ModuleController::class, 'update']);
            Route::delete('/{module}', [ModuleController::class, 'destroy']);
            
            // Chapitres
            Route::get('/{module}/chapitres', [ChapitreController::class, 'index']);
            Route::post('/{module}/chapitres', [ChapitreController::class, 'store']);
        });

        // Chapitres
        Route::prefix('chapitres')->group(function () {
            Route::get('/{chapitre}', [ChapitreController::class, 'show']);
            Route::post('/{chapitre}', [ChapitreController::class, 'update']);
            Route::delete('/{chapitre}', [ChapitreController::class, 'destroy']);
            Route::post('/{chapitre}/complete', [ChapitreController::class, 'marquerComplete']);
            
            // Quiz
            Route::post('/{chapitre}/quiz', [QuizController::class, 'store']);
        });

        // Quiz
        Route::prefix('quiz')->group(function () {
            Route::get('/{quiz}', [QuizController::class, 'show']);
            Route::put('/{quiz}', [QuizController::class, 'update']);
            Route::delete('/{quiz}', [QuizController::class, 'destroy']);
            Route::post('/{quiz}/questions', [QuizController::class, 'addQuestion']);
            Route::post('/{quiz}/soumettre', [QuizController::class, 'soumettre']);
            Route::get('/{quiz}/mes-resultats', [QuizController::class, 'mesResultats']);
        });

        // Inscriptions
        Route::prefix('inscriptions')->group(function () {
            Route::post('/formations/{formation}/demander', [InscriptionController::class, 'demander']);
            Route::post('/{inscription}/approuver', [InscriptionController::class, 'approuver']);
            Route::post('/{inscription}/rejeter', [InscriptionController::class, 'rejeter']);
            Route::post('/{inscription}/bloquer', [InscriptionController::class, 'bloquer']);
            Route::post('/{inscription}/debloquer', [InscriptionController::class, 'debloquer']);
            Route::get('/{inscription}/progression', [InscriptionController::class, 'progression']);
        });

        // ==========================================
        // ROUTES COMMUNAUTÉS
        // ==========================================
        Route::prefix('communautes')->group(function () {
            // MESSAGES DE BASE
            Route::get('/{communaute}', [CommunauteController::class, 'show']);
            Route::get('/{communaute}/messages', [CommunauteController::class, 'messages']);
            Route::post('/{communaute}/messages', [CommunauteController::class, 'envoyerMessage']);
            Route::post('/{communaute}/annonces', [CommunauteController::class, 'envoyerAnnonce']);
            
            // GESTION DES MESSAGES
            Route::put('/messages/{message}', [CommunauteController::class, 'updateMessage']);
            Route::delete('/messages/{message}', [CommunauteController::class, 'supprimerMessage']);
            Route::post('/messages/{message}/epingler', [CommunauteController::class, 'epinglerMessage']);
            Route::post('/messages/{message}/desepingler', [CommunauteController::class, 'desepinglerMessage']);
            
            // RÉACTIONS
            Route::post('/messages/{message}/reactions', [CommunauteController::class, 'toggleReaction']);
            
            // THREADS / RÉPONSES
            Route::get('/messages/{message}/replies', [CommunauteController::class, 'getReplies']);
            
            // VUES / READ RECEIPTS
            Route::post('/messages/{message}/view', [CommunauteController::class, 'markAsViewed']);
            
            // MENTIONS
            Route::get('/mentions/unread', [CommunauteController::class, 'getUnreadMentions']);
            Route::post('/mentions/{mention}/read', [CommunauteController::class, 'markMentionAsRead']);
            
            // RECHERCHE
            Route::get('/{communaute}/search', [CommunauteController::class, 'searchMessages']);
            
            // STATISTIQUES
            Route::get('/{communaute}/stats', [CommunauteController::class, 'getStats']);
            
            // MEMBRES
            Route::get('/{communaute}/membres', [CommunauteController::class, 'membres']);
            Route::post('/{communaute}/membres/{userId}/muter', [CommunauteController::class, 'muterMembre']);
            Route::post('/{communaute}/membres/{userId}/demuter', [CommunauteController::class, 'demuterMembre']);
        });

        // Statistiques Formateur
        Route::prefix('statistiques')->group(function () {
            Route::get('/', [StatistiquesController::class, 'index']);
            Route::get('/revenus', [StatistiquesController::class, 'revenus']);
            Route::get('/apprenants', [StatistiquesController::class, 'apprenants']);
            Route::get('/demandes-en-attente', [StatistiquesController::class, 'demandesEnAttente']);
            Route::get('/graphique-inscriptions', [StatistiquesController::class, 'graphiqueInscriptions']);
        });
    });
});

// ==========================================
// ROUTE DE TEST (À SUPPRIMER EN PRODUCTION)
// ==========================================
Route::get('/test-storage', function () {
    $chapitresPath = storage_path('app/public/chapitres');
    $publicStoragePath = public_path('storage');
    
    $chapitres = [];
    if (file_exists($chapitresPath)) {
        $files = scandir($chapitresPath);
        $chapitres = array_filter($files, function($file) use ($chapitresPath) {
            return is_file($chapitresPath . '/' . $file);
        });
    }
    
    return response()->json([
        'storage_path_exists' => file_exists($chapitresPath),
        'storage_path' => $chapitresPath,
        'public_storage_exists' => file_exists($publicStoragePath),
        'public_storage_is_link' => is_link($publicStoragePath),
        'public_storage_target' => is_link($publicStoragePath) ? readlink($publicStoragePath) : null,
        'files_in_chapitres' => array_values($chapitres),
        'app_url' => config('app.url'),
        'storage_url' => asset('storage'),
    ]);
});