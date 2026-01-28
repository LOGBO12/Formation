<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FormateurPaymentController extends Controller
{
    /**
     * Mettre à jour les informations de paiement du formateur
     */
    public function updatePaymentSettings(Request $request)
    {
        try {
            $request->validate([
                'payment_phone' => 'required|string|min:8|max:20',
                'payment_phone_country' => 'required|in:bj,tg,ci,sn,ml,bf,ne',
            ]);

            $user = $request->user();

            Log::info('💰 Mise à jour payment settings', [
                'user_id' => $user->id,
                'phone' => $request->payment_phone,
                'country' => $request->payment_phone_country,
            ]);

            // Mettre à jour l'utilisateur
            $user->update([
                'payment_phone' => $request->payment_phone,
                'payment_phone_country' => $request->payment_phone_country,
            ]);

            // Recharger l'utilisateur
            $user->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Informations de paiement mises à jour avec succès',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'payment_phone' => $user->payment_phone,
                    'payment_phone_country' => $user->payment_phone_country,
                ],
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Données invalides',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('❌ Erreur updatePaymentSettings: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Obtenir les informations de paiement
     */
    public function getPaymentSettings(Request $request)
    {
        try {
            $user = $request->user();

            return response()->json([
                'success' => true,
                'payment_phone' => $user->payment_phone ?? '',
                'payment_phone_country' => $user->payment_phone_country ?? 'bj',
                'has_payment_setup' => !empty($user->payment_phone),
            ], 200);

        } catch (\Exception $e) {
            Log::error('❌ Erreur getPaymentSettings: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération',
            ], 500);
        }
    }

    /**
     * Historique des paiements reçus (formateur)
     */
    public function paiementsRecus(Request $request)
    {
        try {
            $formateur = $request->user();
            $formations = $formateur->formationsCreees;

            if ($formations->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'paiements' => ['data' => []],
                    'statistiques' => [
                        'total_brut' => 0,
                        'total_commission' => 0,
                        'total_net' => 0,
                    ],
                ]);
            }

            $paiements = \App\Models\Paiement::whereIn('formation_id', $formations->pluck('id'))
                ->where('statut', 'complete')
                ->with(['formation', 'user'])
                ->orderBy('date_paiement', 'desc')
                ->paginate(20);

            // Calculer le total et les commissions
            $totalBrut = 0;
            $totalCommission = 0;
            $totalNet = 0;

            foreach ($paiements as $paiement) {
                $commission = ($paiement->formation->commission_admin / 100) * $paiement->montant;
                $totalBrut += $paiement->montant;
                $totalCommission += $commission;
                $totalNet += ($paiement->montant - $commission);
            }

            return response()->json([
                'success' => true,
                'paiements' => $paiements,
                'statistiques' => [
                    'total_brut' => round($totalBrut, 2),
                    'total_commission' => round($totalCommission, 2),
                    'total_net' => round($totalNet, 2),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Erreur paiementsRecus: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement',
            ], 500);
        }
    }
}