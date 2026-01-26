<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Formation;
use App\Models\Paiement;
use App\Services\FedaPayService;
use Illuminate\Http\Request;
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
    $request->validate([
        'phone_number' => 'required|string',
    ]);

    // Vérifier que la formation est payante
    if ($formation->is_free) {
        return response()->json([
            'success' => false,
            'message' => 'Cette formation est gratuite',
        ], 400);
    }

    // Vérifier que l'utilisateur n'est pas déjà inscrit
    $existingInscription = $request->user()->inscriptions()
        ->where('formation_id', $formation->id)
        ->first();

    if ($existingInscription) {
        return response()->json([
            'success' => false,
            'message' => 'Vous êtes déjà inscrit à cette formation',
        ], 400);
    }

    // Créer la transaction
    $result = $this->fedaPayService->createTransaction(
        $request->user(),
        $formation,
        $request->phone_number
    );

    if (!$result['success']) {
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la création de la transaction',
            'error' => $result['message']
        ], 500);
    }

    return response()->json([
        'success' => true,
        'message' => 'Transaction créée avec succès',
        'paiement_id' => $result['paiement']->id,
        'transaction_id' => $result['transaction']->id,
        'payment_url' => $result['url'], // URL de redirection FedaPay
        'token' => $result['token']
    ], 201);
}

/**
 * Vérifier le statut d'un paiement
 */
public function verifierStatut(Request $request, Paiement $paiement)
{
    // Vérifier que c'est bien le paiement de l'utilisateur
    if ($paiement->user_id !== $request->user()->id) {
        return response()->json([
            'success' => false,
            'message' => 'Non autorisé',
        ], 403);
    }

    $result = $this->fedaPayService->checkTransactionStatus($paiement->fedapay_transaction_id);

    if (!$result['success']) {
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la vérification',
        ], 500);
    }

    return response()->json([
        'success' => true,
        'paiement' => $paiement->fresh(),
        'status' => $result['status']
    ]);
}

/**
 * Callback FedaPay
 */
public function callback(Request $request)
{
    $transactionId = $request->query('transaction_id');
    $paiementId = $request->query('paiement_id');

    if (!$transactionId || !$paiementId) {
        return redirect('/apprenant/mes-formations?payment=error');
    }

    $result = $this->fedaPayService->handleCallback($transactionId, $paiementId);

    if (!$result['success']) {
        return redirect('/apprenant/mes-formations?payment=error');
    }

    $paiement = $result['paiement'];

    if ($paiement->statut === 'complete') {
        return redirect('/apprenant/mes-formations?payment=success&formation_id=' . $paiement->formation_id);
    } else {
        return redirect('/apprenant/mes-formations?payment=pending');
    }
}

/**
 * Webhook FedaPay
 */
public function webhook(Request $request)
{
    $payload = $request->all();

    \Log::info('FedaPay Webhook reçu:', $payload);

    $result = $this->fedaPayService->handleWebhook($payload);

    if ($result['success']) {
        return response()->json(['status' => 'success'], 200);
    }

    return response()->json(['status' => 'error'], 400);
}

/**
 * Historique des paiements de l'utilisateur
 */
public function mesPaiements(Request $request)
{
    $paiements = Paiement::where('user_id', $request->user()->id)
        ->with('formation')
        ->orderBy('created_at', 'desc')
        ->paginate(10);

    return response()->json([
        'success' => true,
        'paiements' => $paiements
    ]);
}
}
