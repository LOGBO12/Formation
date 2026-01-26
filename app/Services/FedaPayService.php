<?php
namespace App\Services;
use FedaPay\FedaPay;
use FedaPay\Transaction;
use FedaPay\Customer;
use App\Models\Paiement;
use App\Models\Formation;
use App\Models\User;
use Illuminate\Support\Str;
class FedaPayService
{
public function __construct()
{
// Configuration FedaPay
FedaPay::setApiKey(config('fedapay.api_key'));
FedaPay::setEnvironment(config('fedapay.environment'));
}
/**
 * Créer une transaction de paiement
 */
public function createTransaction(
    User $user,
    Formation $formation,
    string $phoneNumber,
    array $additionalData = []
) {
    try {
        // Créer d'abord un enregistrement de paiement dans notre DB
        $paiement = Paiement::create([
            'user_id' => $user->id,
            'formation_id' => $formation->id,
            'montant' => $formation->prix,
            'statut' => 'en_attente',
            'methode_paiement' => 'fedapay',
            'phone_number' => $phoneNumber,
            'customer_email' => $user->email,
            'customer_name' => $user->name,
            'metadata' => [
                'formation_titre' => $formation->titre,
                'formation_slug' => $formation->slug,
                'user_email' => $user->email,
                'additional' => $additionalData
            ]
        ]);

        // Créer le client FedaPay
        $customer = Customer::create([
            'firstname' => $user->name,
            'lastname' => '',
            'email' => $user->email,
            'phone_number' => [
                'number' => $phoneNumber,
                'country' => 'bj' // Bénin par défaut
            ]
        ]);

        // Créer la transaction FedaPay
        $transaction = Transaction::create([
            'description' => "Paiement pour: {$formation->titre}",
            'amount' => (int) $formation->prix,
            'currency' => [
                'iso' => config('fedapay.currency')
            ],
            'callback_url' => config('fedapay.callback_url') . '?paiement_id=' . $paiement->id,
            'customer' => [
                'id' => $customer->id
            ]
        ]);

        // Générer le token de paiement
        $token = $transaction->generateToken();

        // Mettre à jour notre paiement avec les infos FedaPay
        $paiement->update([
            'fedapay_transaction_id' => $transaction->id,
            'fedapay_token' => $token->token,
            'fedapay_status' => $transaction->status
        ]);

        return [
            'success' => true,
            'paiement' => $paiement,
            'transaction' => $transaction,
            'token' => $token->token,
            'url' => $token->url // URL de paiement FedaPay
        ];

    } catch (\Exception $e) {
        \Log::error('Erreur FedaPay createTransaction: ' . $e->getMessage());
        
        if (isset($paiement)) {
            $paiement->update([
                'statut' => 'echoue',
                'fedapay_response' => $e->getMessage()
            ]);
        }

        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Vérifier le statut d'une transaction
 */
public function checkTransactionStatus($transactionId)
{
    try {
        $transaction = Transaction::retrieve($transactionId);
        
        return [
            'success' => true,
            'status' => $transaction->status,
            'transaction' => $transaction
        ];
    } catch (\Exception $e) {
        \Log::error('Erreur FedaPay checkTransactionStatus: ' . $e->getMessage());
        
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Traiter le callback de paiement
 */
public function handleCallback($transactionId, $paiementId)
{
    try {
        $paiement = Paiement::findOrFail($paiementId);
        
        // Récupérer le statut de la transaction
        $result = $this->checkTransactionStatus($transactionId);
        
        if (!$result['success']) {
            return $result;
        }

        $transaction = $result['transaction'];
        
        // Mettre à jour le paiement selon le statut
        $this->updatePaiementStatus($paiement, $transaction);

        return [
            'success' => true,
            'paiement' => $paiement->fresh(),
            'status' => $transaction->status
        ];

    } catch (\Exception $e) {
        \Log::error('Erreur FedaPay handleCallback: ' . $e->getMessage());
        
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
     * Mettre à jour le statut du paiement
     */
    private function updatePaiementStatus(Paiement $paiement, $transaction)
    {
        $statusMap = [
            'approved' => 'complete',
            'transferred' => 'complete',
            'pending' => 'en_attente',
            'declined' => 'echoue',
            'canceled' => 'echoue'
        ];

        $newStatus = $statusMap[$transaction->status] ?? 'en_attente';

        $paiement->update([
            'statut' => $newStatus,
            'fedapay_status' => $transaction->status,
            'fedapay_response' => json_encode($transaction),
            'date_paiement' => $newStatus === 'complete' ? now() : null
        ]);

        // Si paiement réussi, créer l'inscription automatiquement
        if ($newStatus === 'complete') {
            $this->createInscription($paiement);
            
            // ⚠️ IMPORTANT : Reverser l'argent au formateur (90%)
            $this->reverserArgentFormateur($paiement);
        }
    }

    /**
     * Reverser l'argent au formateur (automatique)
     */
    private function reverserArgentFormateur(Paiement $paiement)
    {
        try {
            $formation = $paiement->formation;
            $formateur = $formation->formateur;

            // Vérifier que le formateur a configuré son numéro
            if (!$formateur->payment_phone) {
                \Log::warning("Formateur {$formateur->id} n'a pas de numéro de paiement configuré");
                return;
            }

            // Calculer les montants
            $montantTotal = $paiement->montant;
            $commissionAdmin = ($formation->commission_admin / 100) * $montantTotal;
            $montantFormateur = $montantTotal - $commissionAdmin;

            // Créer un payout FedaPay vers le formateur
            $payout = \FedaPay\Payout::create([
                'amount' => (int) $montantFormateur,
                'currency' => [
                    'iso' => config('fedapay.currency')
                ],
                'mode' => 'mtn', // ou 'moov' selon l'opérateur
                'customer' => [
                    'firstname' => $formateur->name,
                    'email' => $formateur->email,
                    'phone_number' => [
                        'number' => $formateur->payment_phone,
                        'country' => $formateur->payment_phone_country
                    ]
                ],
                'description' => "Revenus formation: {$formation->titre}",
            ]);

            // Sauvegarder le payout
            $payout->sendNow();

            \Log::info("✅ Payout créé pour formateur {$formateur->id}: {$montantFormateur} FCFA");

            // Enregistrer la transaction
            \DB::table('formateur_payouts')->insert([
                'paiement_id' => $paiement->id,
                'formateur_id' => $formateur->id,
                'formation_id' => $formation->id,
                'montant_total' => $montantTotal,
                'commission_admin' => $commissionAdmin,
                'montant_formateur' => $montantFormateur,
                'fedapay_payout_id' => $payout->id,
                'statut' => $payout->status,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        } catch (\Exception $e) {
            \Log::error("❌ Erreur payout formateur: " . $e->getMessage());
            
            // Ne pas bloquer le processus, juste logger
            // L'admin pourra faire le payout manuellement si besoin
        }
    }
/**
 * Créer l'inscription après paiement réussi
 */
private function createInscription(Paiement $paiement)
{
    // Vérifier si l'inscription n'existe pas déjà
    $existingInscription = \App\Models\Inscription::where('user_id', $paiement->user_id)
        ->where('formation_id', $paiement->formation_id)
        ->first();

    if ($existingInscription) {
        return $existingInscription;
    }

    // Créer l'inscription
    $inscription = \App\Models\Inscription::create([
        'user_id' => $paiement->user_id,
        'formation_id' => $paiement->formation_id,
        'statut' => 'active',
        'date_approbation' => now(),
        'progres' => 0
    ]);

    // Ajouter l'apprenant à la communauté
    $formation = $paiement->formation;
    if ($formation->communaute) {
        $formation->communaute->ajouterMembre($paiement->user_id, 'membre');
    }

    return $inscription;
}

/**
 * Traiter un webhook FedaPay
 */
public function handleWebhook(array $payload)
{
    try {
        // Vérifier la signature du webhook (sécurité)
        // ...

        $transactionId = $payload['entity']['id'] ?? null;
        $status = $payload['entity']['status'] ?? null;

        if (!$transactionId) {
            throw new \Exception('Transaction ID manquant');
        }

        // Trouver le paiement correspondant
        $paiement = Paiement::where('fedapay_transaction_id', $transactionId)->first();

        if (!$paiement) {
            throw new \Exception('Paiement non trouvé');
        }

        // Récupérer les détails de la transaction
        $transaction = Transaction::retrieve($transactionId);

        // Mettre à jour le statut
        $this->updatePaiementStatus($paiement, $transaction);

        return [
            'success' => true,
            'paiement' => $paiement
        ];

    } catch (\Exception $e) {
        \Log::error('Erreur FedaPay webhook: ' . $e->getMessage());
        
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}
}