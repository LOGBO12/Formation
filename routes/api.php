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
use App\Http\Controllers\Api\FormateurPaymentController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PublicController;
use App\Http\Controllers\Api\AdminContactController;
use App\Http\Controllers\Api\AdminNewsletterController;
use App\Http\Controllers\Api\AdminRevenusController;
use App\Http\Controllers\Api\PublicFormationController;

/*
|--------------------------------------------------------------------------
| API Routes - CORRIGÉES
|--------------------------------------------------------------------------
*/



// ==========================================
// ROUTES PUBLIQUES (pas d'authentification)
// ==========================================

Route::post('/public/contact', [PublicController::class, 'submitContact']);

// Newsletter
Route::post('/public/newsletter/subscribe', [PublicController::class, 'subscribeNewsletter']);
Route::post('/public/newsletter/unsubscribe', [PublicController::class, 'unsubscribeNewsletter']);


// Callbacks FedaPay (DOIVENT ÊTRE AVANT auth:sanctum)
Route::get('/fedapay/callback', [PaiementController::class, 'callback'])->name('fedapay.callback');
Route::post('/fedapay/webhook', [PaiementController::class, 'webhook'])->name('fedapay.webhook');

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);

    Route::post('/verify-email', [AuthController::class, 'verifyEmail']);
    Route::post('/resend-verification', [AuthController::class, 'resendVerification']);

});

// Domaines publics
Route::get('/domaines', [DomaineController::class, 'index']);

// Formation publique par lien
Route::get('/formations/lien/{lienPublic}', [FormationController::class, 'showByLink']);

Route::prefix('public/formations')->group(function () {
    // Liste des formations publiques
    Route::get('/', [PublicFormationController::class, 'index']);
    
    // Détails d'une formation par lien public
    Route::get('/{lienPublic}', [PublicFormationController::class, 'show']);
    
    // Statistiques publiques
    Route::get('/stats/general', [PublicFormationController::class, 'stats']);
});
// ==========================================
// ROUTES PROTÉGÉES (authentification requise)
// ==========================================

Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::post('/auth/confirm-password', [AuthController::class, 'confirmPassword']);
    
    // ==========================================
    // PROFIL ET PARAMÈTRES (NOUVEAU)
    // ==========================================
    Route::prefix('profile')->group(function () {
        Route::post('/update', [ProfileController::class, 'updateProfile']);
        Route::post('/change-password', [ProfileController::class, 'changePassword']);
        Route::post('/update-notifications', [ProfileController::class, 'updateNotifications']);
        Route::get('/download-data', [ProfileController::class, 'downloadData']);
        Route::delete('/delete-account', [ProfileController::class, 'deleteAccount']);
    });

    // Onboarding
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
            Route::get('/dashboard', [ApprenantController::class, 'dashboard']);
            Route::get('/mes-formations', [ApprenantController::class, 'mesFormations']);
            Route::get('/formations-terminees', [ApprenantController::class, 'formationsTerminees']);
            Route::get('/catalogue', [ApprenantController::class, 'catalogue']);
            Route::get('/progression', [ApprenantController::class, 'progression']);
            Route::get('/communautes', [ApprenantController::class, 'mesCommunautes']);
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
            
            //  Paiements et Revenus (CORRIGÉ)
            Route::get('/paiements-recus', [FormateurPaymentController::class, 'paiementsRecus']);
            Route::get('/payment-settings', [FormateurPaymentController::class, 'getPaymentSettings']);
            Route::post('/payment-settings/update', [FormateurPaymentController::class, 'updatePaymentSettings']);
        });

        // ==========================================
        // ROUTES SUPER ADMIN
        // ==========================================
        Route::prefix('admin')->group(function () {
            Route::get('/domaines', [DomaineController::class, 'adminIndex']);
            Route::post('/domaines', [DomaineController::class, 'store']);
            Route::put('/domaines/{domaine}', [DomaineController::class, 'update']);
            Route::delete('/domaines/{domaine}', [DomaineController::class, 'destroy']);
            Route::patch('/domaines/{domaine}/toggle', [DomaineController::class, 'toggleStatus']);
            Route::get('/users', [AdminController::class, 'users']);
            Route::patch('/users/{user}/toggle-status', [AdminController::class, 'toggleUserStatus']);
            Route::get('/formations', [AdminController::class, 'allFormations']);

            // Gestion des contacts
        Route::get('/contacts', [AdminContactController::class, 'index']);
        Route::get('/contacts/{submission}', [AdminContactController::class, 'show']);
        Route::patch('/contacts/{submission}/status', [AdminContactController::class, 'updateStatus']);
        Route::post('/contacts/{submission}/respond', [AdminContactController::class, 'respond']);
        Route::delete('/contacts/{submission}', [AdminContactController::class, 'destroy']);

        // Gestion de la newsletter
        Route::get('/newsletter/subscribers', [AdminNewsletterController::class, 'index']);
        Route::delete('/newsletter/subscribers/{subscriber}', [AdminNewsletterController::class, 'destroy']);
        Route::get('/newsletter/export', [AdminNewsletterController::class, 'export']);

        // Gestion des revenus
        Route::get('/revenus', [AdminRevenusController::class, 'index']);
            Route::get('/revenus/export', [AdminRevenusController::class, 'export']);
            Route::get('/revenus/statistics', [AdminRevenusController::class, 'statistics']);
        
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
            Route::get('/{formation}/modules', [ModuleController::class, 'index']);
            Route::post('/{formation}/modules', [ModuleController::class, 'store']);
            Route::get('/{formation}/apprenants', [InscriptionController::class, 'apprenants']);
        });

        // Modules
        Route::prefix('modules')->group(function () {
            Route::get('/{module}', [ModuleController::class, 'show']);
            Route::put('/{module}', [ModuleController::class, 'update']);
            Route::delete('/{module}', [ModuleController::class, 'destroy']);
            Route::get('/{module}/chapitres', [ChapitreController::class, 'index']);
            Route::post('/{module}/chapitres', [ChapitreController::class, 'store']);
        });

        // Chapitres
        Route::prefix('chapitres')->group(function () {
            Route::get('/{chapitre}', [ChapitreController::class, 'show']);
            Route::post('/{chapitre}', [ChapitreController::class, 'update']);
            Route::delete('/{chapitre}', [ChapitreController::class, 'destroy']);
            Route::post('/{chapitre}/complete', [ChapitreController::class, 'marquerComplete']);
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
            Route::get('/{communaute}', [CommunauteController::class, 'show']);
            Route::get('/{communaute}/messages', [CommunauteController::class, 'messages']);
            Route::post('/{communaute}/messages', [CommunauteController::class, 'envoyerMessage']);
            Route::post('/{communaute}/annonces', [CommunauteController::class, 'envoyerAnnonce']);
            Route::put('/messages/{message}', [CommunauteController::class, 'updateMessage']);
            Route::delete('/messages/{message}', [CommunauteController::class, 'supprimerMessage']);
            Route::post('/messages/{message}/epingler', [CommunauteController::class, 'epinglerMessage']);
            Route::post('/messages/{message}/desepingler', [CommunauteController::class, 'desepinglerMessage']);
            Route::post('/messages/{message}/reactions', [CommunauteController::class, 'toggleReaction']);
            Route::get('/messages/{message}/replies', [CommunauteController::class, 'getReplies']);
            Route::post('/messages/{message}/view', [CommunauteController::class, 'markAsViewed']);
            Route::get('/mentions/unread', [CommunauteController::class, 'getUnreadMentions']);
            Route::post('/mentions/{mention}/read', [CommunauteController::class, 'markMentionAsRead']);
            Route::get('/{communaute}/search', [CommunauteController::class, 'searchMessages']);
            Route::get('/{communaute}/stats', [CommunauteController::class, 'getStats']);
            Route::get('/{communaute}/membres', [CommunauteController::class, 'membres']);
            Route::post('/{communaute}/membres/{userId}/muter', [CommunauteController::class, 'muterMembre']);
            Route::post('/{communaute}/membres/{userId}/demuter', [CommunauteController::class, 'demuterMembre']);
        });

        // 🔔 NOTIFICATIONS
        Route::middleware('auth:sanctum')->group(function () {
        Route::prefix('notifications')->group(function () {
        // Liste des notifications
        Route::get('/', [NotificationController::class, 'index']);
        
        // Notifications récentes (5 dernières)
        Route::get('/recentes', [NotificationController::class, 'recentes']);
        
        // Compter les non lues
        Route::get('/count', [NotificationController::class, 'compterNonLues']);
        
        // Marquer une notification comme lue
        Route::patch('/{notification}/marquer-lu', [NotificationController::class, 'marquerCommeLue']);
        
        // Marquer toutes comme lues
        Route::post('/marquer-tout-lu', [NotificationController::class, 'marquerToutCommeLu']);
        
        // Supprimer une notification
        Route::delete('/{notification}', [NotificationController::class, 'supprimer']);
        
        // Supprimer toutes les notifications lues
        Route::delete('/supprimer-lues', [NotificationController::class, 'supprimerLues']);
    });
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