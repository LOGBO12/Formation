<?php

namespace App\Services;

use App\Models\Paiement;
use App\Models\User;
use App\Models\Inscription;
use App\Models\FormateurPayout;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use FedaPay\FedaPay;
use FedaPay\Transaction;

class FedaPayService
{
    public function __construct()
    {
        // Configuration FedaPay
        FedaPay::setApiKey(config('fedapay.api_key'));
        FedaPay::setEnvironment(config('fedapay.environment')); // 'sandbox' ou 'live'
    }

    /**
     * Créer une transaction FedaPay
     * 
     * @param Paiement $paiement - Le paiement à traiter
     * @param string $phoneNumber - Numéro de téléphone pour Mobile Money
     * @return Transaction|null
     */
    public function createTransaction(Paiement $paiement, string $phoneNumber)
    {
        try {
            Log::info('FedaPay createTransaction début', [
                'paiement_id' => $paiement->id,
                'montant' => $paiement->montant,
                'phone' => $phoneNumber,
            ]);

            // Récupérer l'utilisateur et la formation
            $user = $paiement->user;
            $formation = $paiement->formation;

            if (!$user || !$formation) {
                Log::error('User ou Formation manquant', [
                    'user' => $user ? $user->id : 'null',
                    'formation' => $formation ? $formation->id : 'null',
                ]);
                return null;
            }

            // Préparer les données de la transaction
            $transactionData = [
                'description' => "Achat formation: {$formation->titre}",
                'amount' => (int) $paiement->montant, // Montant en FCFA
                'currency' => [
                    'iso' => config('fedapay.currency', 'XOF')
                ],
                'callback_url' => route('fedapay.callback'),
                'customer' => [
                    'firstname' => $user->name,
                    'lastname' => $user->name,
                    'email' => $user->email,
                    'phone_number' => [
                        'number' => $phoneNumber,
                        'country' => 'BJ' // Bénin par défaut
                    ]
                ],
            ];

            Log::info('FedaPay transaction data', $transactionData);

            // Créer la transaction sur FedaPay
            $transaction = Transaction::create($transactionData);

            Log::info('FedaPay transaction créée', [
                'transaction_id' => $transaction->id,
                'url' => $transaction->url ?? 'N/A',
                'status' => $transaction->status ?? 'N/A',
            ]);

            // Mettre à jour le paiement avec les infos FedaPay
            $paiement->update([
                'transaction_id' => $transaction->id,
                'payment_url' => $transaction->url ?? null,
                'fedapay_response' => [
                    'id' => $transaction->id,
                    'status' => $transaction->status ?? null,
                    'reference' => $transaction->reference ?? null,
                    'created_at' => $transaction->created_at ?? null,
                ],
            ]);

            // Générer le token de paiement
            $token = $transaction->generateToken();

            Log::info('FedaPay token généré', [
                'token_url' => $token->url ?? 'N/A',
            ]);

            // Mettre à jour l'URL de paiement avec le token
            if (isset($token->url)) {
                $paiement->update(['payment_url' => $token->url]);
            }

            return $transaction;

        } catch (\FedaPay\Error\ApiConnection $e) {
            Log::error('FedaPay createTransaction ApiConnection Error: ' . $e->getMessage(), [
                'paiement_id' => $paiement->id,
            ]);
            return null;

        } catch (\FedaPay\Error\InvalidRequest $e) {
            Log::error('FedaPay createTransaction InvalidRequest Error: ' . $e->getMessage(), [
                'paiement_id' => $paiement->id,
                'errors' => $e->getErrorMessage(),
            ]);
            return null;

        } catch (\Exception $e) {
            Log::error('FedaPay createTransaction Error: ' . $e->getMessage(), [
                'paiement_id' => $paiement->id,
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Vérifier le statut d'une transaction
     */
    public function checkTransactionStatus(Paiement $paiement)
    {
        try {
            if (!$paiement->transaction_id) {
                Log::warning('Pas de transaction_id pour vérifier le statut', [
                    'paiement_id' => $paiement->id,
                ]);
                return false;
            }

            // Récupérer la transaction depuis FedaPay
            $transaction = Transaction::retrieve($paiement->transaction_id);

            Log::info('FedaPay transaction status', [
                'transaction_id' => $transaction->id,
                'status' => $transaction->status,
            ]);

            // Mettre à jour le paiement selon le statut
            $this->updatePaiementStatus($paiement, $transaction);

            return true;

        } catch (\Exception $e) {
            Log::error('Erreur checkTransactionStatus: ' . $e->getMessage(), [
                'paiement_id' => $paiement->id,
            ]);
            return false;
        }
    }

    /**
     * Gérer le callback FedaPay
     */
    public function handleCallback(array $data)
    {
        try {
            Log::info('FedaPay handleCallback', ['data' => $data]);

            $transactionId = $data['transaction_id'] ?? $data['id'] ?? null;

            if (!$transactionId) {
                return [
                    'success' => false,
                    'message' => 'Transaction ID manquant',
                ];
            }

            // Récupérer le paiement
            $paiement = Paiement::where('transaction_id', $transactionId)->first();

            if (!$paiement) {
                return [
                    'success' => false,
                    'message' => 'Paiement introuvable',
                ];
            }

            // Vérifier le statut sur FedaPay
            $this->checkTransactionStatus($paiement);

            return [
                'success' => true,
                'message' => 'Callback traité',
                'paiement' => $paiement,
            ];

        } catch (\Exception $e) {
            Log::error('Erreur handleCallback: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur serveur',
            ];
        }
    }

    /**
     * Gérer le webhook FedaPay
     */
    public function handleWebhook(array $data)
    {
        try {
            Log::info('FedaPay handleWebhook', ['data' => $data]);

            $event = $data['event'] ?? null;
            $transactionData = $data['data'] ?? $data['transaction'] ?? null;

            if (!$transactionData || !isset($transactionData['id'])) {
                return [
                    'success' => false,
                    'message' => 'Données de transaction manquantes',
                ];
            }

            $transactionId = $transactionData['id'];

            // Récupérer le paiement
            $paiement = Paiement::where('transaction_id', $transactionId)->first();

            if (!$paiement) {
                Log::warning('Paiement non trouvé pour transaction: ' . $transactionId);
                return [
                    'success' => false,
                    'message' => 'Paiement introuvable',
                ];
            }

            // Traiter selon l'événement
            switch ($event) {
                case 'transaction.approved':
                    $this->updatePaiementStatus($paiement, (object) $transactionData);
                    break;

                case 'transaction.declined':
                case 'transaction.canceled':
                    $paiement->update([
                        'statut' => 'echec',
                        'fedapay_response' => $transactionData,
                    ]);
                    break;

                default:
                    Log::info('Événement webhook non géré: ' . $event);
            }

            return [
                'success' => true,
                'message' => 'Webhook traité',
            ];

        } catch (\Exception $e) {
            Log::error('Erreur handleWebhook: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return [
                'success' => false,
                'message' => 'Erreur serveur',
            ];
        }
    }

    /**
     * Mettre à jour le statut du paiement selon FedaPay
     */
    protected function updatePaiementStatus(Paiement $paiement, $transaction)
    {
        try {
            $status = $transaction->status ?? 'unknown';

            Log::info('updatePaiementStatus', [
                'paiement_id' => $paiement->id,
                'fedapay_status' => $status,
            ]);

            // Mapper les statuts FedaPay vers nos statuts
            $statusMap = [
                'approved' => 'complete',
                'completed' => 'complete',
                'declined' => 'echec',
                'canceled' => 'annule',
                'pending' => 'en_attente',
            ];

            $newStatus = $statusMap[$status] ?? 'en_attente';

            // Mettre à jour le paiement
            $paiement->update([
                'statut' => $newStatus,
                'fedapay_response' => [
                    'id' => $transaction->id ?? null,
                    'status' => $status,
                    'reference' => $transaction->reference ?? null,
                    'updated_at' => now()->toIso8601String(),
                ],
            ]);

            // Si le paiement est complété, créer l'inscription et reverser au formateur
            if ($newStatus === 'complete') {
                DB::transaction(function () use ($paiement) {
                    // Créer l'inscription automatiquement
                    $this->createInscription($paiement);

                    // Reverser l'argent au formateur (90%)
                    $this->reverserArgentFormateur($paiement);
                });
            }

            return true;

        } catch (\Exception $e) {
            Log::error('Erreur updatePaiementStatus: ' . $e->getMessage(), [
                'paiement_id' => $paiement->id,
            ]);
            return false;
        }
    }

    /**
     * Créer automatiquement l'inscription après paiement réussi
     */
    protected function createInscription(Paiement $paiement)
    {
        try {
            // Vérifier si l'inscription n'existe pas déjà
            $inscriptionExistante = Inscription::where('user_id', $paiement->user_id)
                ->where('formation_id', $paiement->formation_id)
                ->whereIn('statut', ['approuvee', 'en_cours', 'terminee'])
                ->exists();

            if ($inscriptionExistante) {
                Log::info('Inscription déjà existante', [
                    'user_id' => $paiement->user_id,
                    'formation_id' => $paiement->formation_id,
                ]);
                return;
            }

            // Créer l'inscription approuvée automatiquement
            $inscription = Inscription::create([
                'user_id' => $paiement->user_id,
                'formation_id' => $paiement->formation_id,
                'statut' => 'approuvee',
                'date_inscription' => now(),
            ]);

            Log::info('Inscription créée automatiquement après paiement', [
                'inscription_id' => $inscription->id,
                'paiement_id' => $paiement->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur createInscription: ' . $e->getMessage(), [
                'paiement_id' => $paiement->id,
            ]);
        }
    }

    /**
     * Reverser 90% du montant au formateur
     */
    protected function reverserArgentFormateur(Paiement $paiement)
    {
        try {
            $formation = $paiement->formation;
            $formateur = $formation->formateur;

            if (!$formateur) {
                Log::error('Formateur non trouvé pour la formation', [
                    'formation_id' => $formation->id,
                ]);
                return;
            }

            // Calculer les montants
            $montantBrut = $paiement->montant;
            $commission = config('fedapay.default_commission', 10); // 10% par défaut
            $montantCommission = ($montantBrut * $commission) / 100;
            $montantFormateur = $montantBrut - $montantCommission;

            // Vérifier si le payout n'existe pas déjà
            $payoutExistant = FormateurPayout::where('paiement_id', $paiement->id)->exists();

            if ($payoutExistant) {
                Log::info('Payout déjà existant', ['paiement_id' => $paiement->id]);
                return;
            }

            // Créer le payout
            $payout = FormateurPayout::create([
                'formateur_id' => $formateur->id,
                'paiement_id' => $paiement->id,
                'formation_id' => $formation->id,
                'montant_brut' => $montantBrut,
                'commission_plateforme' => $montantCommission,
                'montant_net' => $montantFormateur,
                'statut' => 'en_attente',
                'methode_paiement' => 'mobile_money',
                'numero_destinataire' => $formateur->mobile_money_number ?? null,
            ]);

            Log::info('Payout créé pour le formateur', [
                'payout_id' => $payout->id,
                'formateur_id' => $formateur->id,
                'montant_net' => $montantFormateur,
            ]);

            // TODO: Intégrer l'API de paiement automatique vers Mobile Money
            // Pour l'instant, le statut reste "en_attente" jusqu'au traitement manuel

        } catch (\Exception $e) {
            Log::error('Erreur reverserArgentFormateur: ' . $e->getMessage(), [
                'paiement_id' => $paiement->id,
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}