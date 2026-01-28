<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Paiement;
use App\Models\Formation;
use App\Services\FedaPayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class PaiementController extends Controller
{
    protected $fedaPayService;

    public function __construct(FedaPayService $fedaPayService)
    {
        $this->fedaPayService = $fedaPayService;
    }

    /**
     * Initier un paiement pour une formation
     */
    public function initierPaiement(Request $request, Formation $formation)
    {
        try {
            $request->validate([
                'phone_number' => 'required|string|min:8',
            ]);

            $user = $request->user();

            // Vérifier si l'utilisateur est déjà inscrit
            $inscriptionExistante = $formation->inscriptions()
                ->where('user_id', $user->id)
                ->whereIn('statut', ['active', 'approuvee', 'en_cours', 'terminee'])
                ->exists();

            if ($inscriptionExistante) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous êtes déjà inscrit à cette formation',
                ], 400);
            }

            // Vérifier si la formation est gratuite
            if ($formation->is_free) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cette formation est gratuite, aucun paiement requis',
                ], 400);
            }

            // Vérifier si un paiement est déjà en cours
            $paiementEnCours = Paiement::where('user_id', $user->id)
                ->where('formation_id', $formation->id)
                ->where('statut', 'en_attente')
                ->where('created_at', '>=', now()->subMinutes(15))
                ->first();

            if ($paiementEnCours && $paiementEnCours->transaction_id) {
                return response()->json([
                    'success' => true,
                    'message' => 'Un paiement est déjà en cours',
                    'payment_url' => $paiementEnCours->payment_url,
                    'paiement' => $paiementEnCours,
                ], 200);
            }

            // Créer le paiement
            $paiement = Paiement::create([
                'user_id' => $user->id,
                'formation_id' => $formation->id,
                'montant' => $formation->prix,
                'statut' => 'en_attente',
                'methode_paiement' => 'fedapay',
                'metadata' => [
                    'phone_number' => $request->phone_number,
                    'formation_titre' => $formation->titre,
                    'user_name' => $user->name,
                    'user_email' => $user->email,
                ],
            ]);

            // Créer la transaction FedaPay
            $transaction = $this->fedaPayService->createTransaction(
                $paiement,
                $request->phone_number
            );

            if (!$transaction) {
                $paiement->update(['statut' => 'echec']);
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur lors de la création de la transaction',
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Transaction créée avec succès',
                'payment_url' => $paiement->payment_url,
                'paiement' => $paiement,
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Données invalides',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erreur initierPaiement: ' . $e->getMessage(), [
                'formation_id' => $formation->id,
                'user_id' => $request->user()->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'initialisation du paiement: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Vérifier le statut d'un paiement
     */
    public function verifierStatut(Request $request, Paiement $paiement)
    {
        try {
            // Vérifier que le paiement appartient à l'utilisateur
            if ($paiement->user_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès non autorisé',
                ], 403);
            }

            // Si le paiement a un transaction_id, vérifier le statut sur FedaPay
            if ($paiement->transaction_id) {
                $this->fedaPayService->checkTransactionStatus($paiement);
                $paiement->refresh();
            }

            return response()->json([
                'success' => true,
                'paiement' => $paiement->load('formation'),
            ], 200);

        } catch (\Exception $e) {
            Log::error('Erreur verifierStatut: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la vérification du statut',
            ], 500);
        }
    }

    /**
     * ⚠️ CALLBACK CORRIGÉ - Redirection vers le frontend avec les bons paramètres
     */
    public function callback(Request $request)
    {
        try {
            Log::info('📞 Callback FedaPay reçu', [
                'query_params' => $request->all(),
                'headers' => $request->headers->all(),
            ]);

            // Récupérer l'ID de transaction de différentes manières possibles
            $transactionId = $request->query('id') 
                ?? $request->query('transaction_id') 
                ?? $request->query('reference');

            if (!$transactionId) {
                Log::warning('⚠️ Transaction ID manquant dans callback', [
                    'all_params' => $request->all()
                ]);
                
                return redirect(config('app.frontend_url') . '/payment/callback?payment=error&message=Transaction+ID+manquant');
            }

            Log::info('🔍 Recherche du paiement', ['transaction_id' => $transactionId]);

            // Récupérer le paiement
            $paiement = Paiement::where('transaction_id', $transactionId)->first();

            if (!$paiement) {
                Log::warning('⚠️ Paiement non trouvé', ['transaction_id' => $transactionId]);
                
                return redirect(
                    config('app.frontend_url') . '/payment/callback?payment=error&message=Paiement+introuvable'
                );
            }

            Log::info('✅ Paiement trouvé', [
                'paiement_id' => $paiement->id,
                'statut_actuel' => $paiement->statut,
            ]);

            // Vérifier le statut sur FedaPay
            $this->fedaPayService->checkTransactionStatus($paiement);
            $paiement->refresh();

            Log::info('🔄 Statut mis à jour', [
                'paiement_id' => $paiement->id,
                'nouveau_statut' => $paiement->statut,
            ]);

            // Construire l'URL de redirection avec les bons paramètres
            $frontendUrl = config('app.frontend_url', 'http://localhost:5173');
            $redirectUrl = $frontendUrl . '/payment/callback';

            // Paramètres selon le statut
            if ($paiement->statut === 'complete') {
                $params = [
                    'payment' => 'success',
                    'transaction_id' => $transactionId,
                    'formation_id' => $paiement->formation_id,
                    'amount' => $paiement->montant,
                ];
            } elseif ($paiement->statut === 'en_attente') {
                $params = [
                    'payment' => 'pending',
                    'transaction_id' => $transactionId,
                    'formation_id' => $paiement->formation_id,
                ];
            } else {
                $params = [
                    'payment' => 'error',
                    'transaction_id' => $transactionId,
                    'message' => 'Le paiement n\'a pas pu être validé',
                ];
            }

            $finalUrl = $redirectUrl . '?' . http_build_query($params);

            Log::info('➡️ Redirection vers', ['url' => $finalUrl]);

            return redirect($finalUrl);

        } catch (\Exception $e) {
            Log::error('❌ Erreur callback: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            
            return redirect(
                config('app.frontend_url') . '/payment/callback?payment=error&message=Erreur+serveur'
            );
        }
    }

    /**
     * Webhook FedaPay (notifications serveur)
     */
    public function webhook(Request $request)
    {
        try {
            Log::info('🔔 Webhook FedaPay reçu', ['data' => $request->all()]);

            // Traiter le webhook
            $result = $this->fedaPayService->handleWebhook($request->all());

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Webhook traité avec succès',
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? 'Erreur lors du traitement',
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error('❌ Erreur webhook: ' . $e->getMessage(), [
                'data' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur serveur',
            ], 500);
        }
    }

    /**
     * Liste des paiements de l'utilisateur
     */
    public function mesPaiements(Request $request)
    {
        try {
            $paiements = Paiement::where('user_id', $request->user()->id)
                ->with(['formation.domaine', 'formation.formateur'])
                ->orderBy('created_at', 'desc')
                ->paginate(10);

            // Statistiques
            $stats = [
                'total_depense' => Paiement::where('user_id', $request->user()->id)
                    ->where('statut', 'complete')
                    ->sum('montant'),
                'paiements_reussis' => Paiement::where('user_id', $request->user()->id)
                    ->where('statut', 'complete')
                    ->count(),
                'paiements_en_attente' => Paiement::where('user_id', $request->user()->id)
                    ->where('statut', 'en_attente')
                    ->count(),
            ];

            return response()->json([
                'success' => true,
                'paiements' => $paiements,
                'stats' => $stats,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Erreur mesPaiements: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des paiements',
            ], 500);
        }
    }
}