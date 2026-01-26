<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class FormateurPaymentController extends Controller
{
    /**
     * Mettre à jour les informations de paiement du formateur
     */
    public function updatePaymentSettings(Request $request)
    {
        $request->validate([
            'payment_phone' => 'required|string|max:20',
            'payment_phone_country' => 'required|in:bj,tg,ci,sn,ml,bf,ne',
        ]);

        $user = $request->user();

        $user->update([
            'payment_phone' => $request->payment_phone,
            'payment_phone_country' => $request->payment_phone_country,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Informations de paiement mises à jour',
            'user' => $user,
        ]);
    }

    /**
     * Obtenir les informations de paiement
     */
    public function getPaymentSettings(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'payment_phone' => $user->payment_phone,
            'payment_phone_country' => $user->payment_phone_country,
            'has_payment_setup' => !empty($user->payment_phone),
        ]);
    }

    /**
     * Historique des paiements reçus (formateur)
     */
    public function paiementsRecus(Request $request)
    {
        $formateur = $request->user();
        $formations = $formateur->formationsCreees;

        $paiements = \App\Models\Paiement::whereIn('formation_id', $formations->pluck('id'))
            ->where('statut', 'complete')
            ->with(['formation', 'user'])
            ->orderBy('date_paiement', 'desc')
            ->paginate(20);

        // Calculer le total et les commissions
        $totalBrut = $paiements->sum('montant');
        $totalCommission = 0;
        $totalNet = 0;

        foreach ($paiements as $paiement) {
            $commission = ($paiement->formation->commission_admin / 100) * $paiement->montant;
            $totalCommission += $commission;
            $totalNet += ($paiement->montant - $commission);
        }

        return response()->json([
            'success' => true,
            'paiements' => $paiements,
            'statistiques' => [
                'total_brut' => $totalBrut,
                'total_commission' => $totalCommission,
                'total_net' => $totalNet,
            ],
        ]);
    }
}