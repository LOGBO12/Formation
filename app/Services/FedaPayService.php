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
        
        Log::info('🔧 FedaPay Service initialisé', [
            'environment' => config('fedapay.environment'),
            'has_api_key' => !empty(config('fedapay.api_key')),
        ]);
    }

    /**
     * Créer une transaction FedaPay
     */
    public function createTransaction(Paiement $paiement, string $phoneNumber)
    {
        try {
            Log::info('🚀 FedaPay createTransaction début', [
                'paiement_id' => $paiement->id,
                'montant' => $paiement->montant,
                'phone' => $phoneNumber,
            ]);

            // Récupérer l'utilisateur et la formation
            $user = $paiement->user;
            $formation = $paiement->formation;

            if (!$user || !$formation) {
                Log::error('❌ User ou Formation manquant', [
                    'user' => $user ? $user->id : 'null',
                    'formation' => $formation ? $formation->id : 'null',
                ]);
                return null;
            }

            // Nettoyer le numéro de téléphone
            $cleanPhone = $this->cleanPhoneNumber($phoneNumber);
            
            Log::info('📱 Numéro nettoyé', [
                'original' => $phoneNumber,
                'cleaned' => $cleanPhone,
            ]);

            // Préparer les données de la transaction
            $transactionData = [
                'description' => "Formation: {$formation->titre}",
                'amount' => (int) $paiement->montant,
                'currency' => [
                    'iso' => config('fedapay.currency', 'XOF')
                ],
                'callback_url' => config('app.url') . '/api/fedapay/callback',
                'customer' => [
                    'firstname' => $user->name,
                    'lastname' => $user->name,
                    'email' => $user->email,
                    'phone_number' => [
                        'number' => $cleanPhone,
                        'country' => 'BJ'
                    ]
                ],
            ];

            Log::info('📦 FedaPay transaction data', $transactionData);

            // Créer la transaction sur FedaPay
            $transaction = Transaction::create($transactionData);

            Log::info('✅ FedaPay transaction créée', [
                'transaction_id' => $transaction->id,
                'status' => $transaction->status ?? 'N/A',
            ]);

            // Mettre à jour le paiement
            $paiement->update([
                'transaction_id' => $transaction->id,
                'fedapay_response' => [
                    'id' => $transaction->id,
                    'status' => $transaction->status ?? null,
                    'reference' => $transaction->reference ?? null,
                    'created_at' => now()->toIso8601String(),
                ],
            ]);

            // Générer le token de paiement
            $token = $transaction->generateToken();

            Log::info('🔑 FedaPay token généré', [
                'has_url' => isset($token->url),
                'url' => $token->url ?? 'N/A',
            ]);

            // Mettre à jour l'URL de paiement
            if (isset($token->url)) {
                $paiement->update(['payment_url' => $token->url]);
                
                return $transaction;
            } else {
                Log::error('❌ Pas d\'URL de paiement générée');
                $paiement->update(['statut' => 'echec']);
                return null;
            }

        } catch (\FedaPay\Error\ApiConnection $e) {
            Log::error('❌ FedaPay ApiConnection Error', [
                'message' => $e->getMessage(),
                'paiement_id' => $paiement->id,
            ]);
            $paiement->update([
                'statut' => 'echec',
                'fedapay_response' => ['error' => $e->getMessage()],
            ]);
            return null;

        } catch (\FedaPay\Error\InvalidRequest $e) {
            Log::error('❌ FedaPay InvalidRequest Error', [
                'message' => $e->getMessage(),
                'errors' => method_exists($e, 'getErrorMessage') ? $e->getErrorMessage() : 'N/A',
                'paiement_id' => $paiement->id,
            ]);
            $paiement->update([
                'statut' => 'echec',
                'fedapay_response' => ['error' => $e->getMessage()],
            ]);
            return null;

        } catch (\Exception $e) {
            Log::error('❌ FedaPay createTransaction Error', [
                'message' => $e->getMessage(),
                'paiement_id' => $paiement->id,
                'trace' => $e->getTraceAsString(),
            ]);
            $paiement->update([
                'statut' => 'echec',
                'fedapay_response' => ['error' => $e->getMessage()],
            ]);
            return null;
        }
    }

    /**
     * Nettoyer le numéro de téléphone
     */
    private function cleanPhoneNumber($phone)
    {
        // Enlever tous les caractères non numériques sauf le +
        $phone = preg_replace('/[^\d+]/', '', $phone);
        
        // Si le numéro commence par +229, le garder tel quel
        if (strpos($phone, '+229') === 0) {
            return $phone;
        }
        
        // Si le numéro commence par 00229, remplacer par +229
        if (strpos($phone, '00229') === 0) {
            return '+' . substr($phone, 2);
        }
        
        // Si le numéro commence par 229, ajouter le +
        if (strpos($phone, '229') === 0) {
            return '+' . $phone;
        }
        
        // Sinon, ajouter +229 devant
        return '+229' . ltrim($phone, '0');
    }

    /**
     * Vérifier le statut d'une transaction
     */
    public function checkTransactionStatus(Paiement $paiement)
    {
        try {
            if (!$paiement->transaction_id) {
                Log::warning('⚠️ Pas de transaction_id', [
                    'paiement_id' => $paiement->id,
                ]);
                return false;
            }

            // Récupérer la transaction depuis FedaPay
            $transaction = Transaction::retrieve($paiement->transaction_id);

            Log::info('🔍 FedaPay transaction status', [
                'transaction_id' => $transaction->id,
                'status' => $transaction->status,
            ]);

            // Mettre à jour le paiement
            $this->updatePaiementStatus($paiement, $transaction);

            return true;

        } catch (\Exception $e) {
            Log::error('❌ Erreur checkTransactionStatus', [
                'message' => $e->getMessage(),
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
            Log::info('📞 FedaPay handleCallback', ['data' => $data]);

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
            Log::error('❌ Erreur handleCallback: ' . $e->getMessage());
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
            Log::info('🔔 FedaPay handleWebhook', ['data' => $data]);

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
                Log::warning('⚠️ Paiement non trouvé pour transaction: ' . $transactionId);
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
                    Log::info('ℹ️ Événement webhook non géré: ' . $event);
            }

            return [
                'success' => true,
                'message' => 'Webhook traité',
            ];

        } catch (\Exception $e) {
            Log::error('❌ Erreur handleWebhook', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return [
                'success' => false,
                'message' => 'Erreur serveur',
            ];
        }
    }

    /**
     * Mettre à jour le statut du paiement
     */
    protected function updatePaiementStatus(Paiement $paiement, $transaction)
    {
        try {
            $status = $transaction->status ?? 'unknown';

            Log::info('🔄 updatePaiementStatus', [
                'paiement_id' => $paiement->id,
                'fedapay_status' => $status,
            ]);

            // Mapper les statuts FedaPay
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
                'date_paiement' => $newStatus === 'complete' ? now() : null,
                'fedapay_response' => [
                    'id' => $transaction->id ?? null,
                    'status' => $status,
                    'reference' => $transaction->reference ?? null,
                    'updated_at' => now()->toIso8601String(),
                ],
            ]);

            // Si paiement complété, créer inscription et reverser au formateur
            if ($newStatus === 'complete') {
                DB::transaction(function () use ($paiement) {
                    $this->createInscription($paiement);
                    $this->reverserArgentFormateur($paiement);
                });
            }

            return true;

        } catch (\Exception $e) {
            Log::error('❌ Erreur updatePaiementStatus', [
                'message' => $e->getMessage(),
                'paiement_id' => $paiement->id,
            ]);
            return false;
        }
    }

    /**
     * Créer l'inscription automatiquement
     */
    protected function createInscription(Paiement $paiement)
    {
        try {
            // Vérifier si inscription existe déjà
            $inscriptionExistante = Inscription::where('user_id', $paiement->user_id)
                ->where('formation_id', $paiement->formation_id)
                ->whereIn('statut', ['active', 'approuvee', 'en_cours', 'terminee'])
                ->exists();

            if ($inscriptionExistante) {
                Log::info('ℹ️ Inscription déjà existante', [
                    'user_id' => $paiement->user_id,
                    'formation_id' => $paiement->formation_id,
                ]);
                return;
            }

            // Créer l'inscription
            $inscription = Inscription::create([
                'user_id' => $paiement->user_id,
                'formation_id' => $paiement->formation_id,
                'statut' => 'active',
                'date_approbation' => now(),
            ]);

            Log::info('✅ Inscription créée', [
                'inscription_id' => $inscription->id,
                'paiement_id' => $paiement->id,
            ]);

            // Ajouter à la communauté
            $this->ajouterACommunaute($paiement->formation, $paiement->user_id);

        } catch (\Exception $e) {
            Log::error('❌ Erreur createInscription', [
                'message' => $e->getMessage(),
                'paiement_id' => $paiement->id,
            ]);
        }
    }

    /**
     * Ajouter à la communauté
     */
    protected function ajouterACommunaute($formation, $userId)
    {
        try {
            if (!$formation->communaute) {
                $communaute = \App\Models\Communaute::create([
                    'formation_id' => $formation->id,
                    'nom' => 'Communauté - ' . $formation->titre,
                    'description' => 'Communauté des apprenants',
                ]);

                // Ajouter le formateur comme admin
                $communaute->ajouterMembre($formation->formateur_id, 'admin');
            } else {
                $communaute = $formation->communaute;
            }

            // Ajouter l'apprenant
            $communaute->ajouterMembre($userId, 'membre');

        } catch (\Exception $e) {
            Log::error('❌ Erreur ajouterACommunaute: ' . $e->getMessage());
        }
    }

    /**
     * Reverser l'argent au formateur
     */
    protected function reverserArgentFormateur(Paiement $paiement)
    {
        try {
            $formation = $paiement->formation;
            $formateur = $formation->formateur;

            if (!$formateur) {
                Log::error('❌ Formateur non trouvé', [
                    'formation_id' => $formation->id,
                ]);
                return;
            }

            // Calculer les montants
            $montantBrut = $paiement->montant;
            $commission = $formation->commission_admin ?? 10;
            $montantCommission = ($montantBrut * $commission) / 100;
            $montantFormateur = $montantBrut - $montantCommission;

            // Vérifier si le payout existe déjà
            $payoutExistant = FormateurPayout::where('paiement_id', $paiement->id)->exists();

            if ($payoutExistant) {
                Log::info('ℹ️ Payout déjà existant', ['paiement_id' => $paiement->id]);
                return;
            }

            // Créer le payout
            $payout = FormateurPayout::create([
                'formateur_id' => $formateur->id,
                'paiement_id' => $paiement->id,
                'formation_id' => $formation->id,
                'montant_total' => $montantBrut,
                'commission_admin' => $montantCommission,
                'montant_formateur' => $montantFormateur,
                'statut' => 'pending',
            ]);

            Log::info('✅ Payout créé', [
                'payout_id' => $payout->id,
                'formateur_id' => $formateur->id,
                'montant_net' => $montantFormateur,
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Erreur reverserArgentFormateur', [
                'message' => $e->getMessage(),
                'paiement_id' => $paiement->id,
            ]);
        }
    }
}