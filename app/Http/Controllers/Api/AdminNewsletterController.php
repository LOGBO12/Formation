<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminNewsletterController extends Controller
{
    /**
     * Liste des abonnés newsletter
     */
    public function index(Request $request)
    {
        if (!$request->user()->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé',
            ], 403);
        }

        $status = $request->input('status', 'all');
        $search = $request->input('search', '');

        $query = NewsletterSubscriber::orderBy('subscribed_at', 'desc');

        // Filtre par statut
        if ($status === 'active') {
            $query->active();
        } elseif ($status === 'inactive') {
            $query->inactive();
        }

        // Recherche
        if ($search) {
            $query->where('email', 'like', "%{$search}%");
        }

        $subscribers = $query->paginate(50);

        // Statistiques
        $stats = [
            'total' => NewsletterSubscriber::count(),
            'active' => NewsletterSubscriber::active()->count(),
            'inactive' => NewsletterSubscriber::inactive()->count(),
            'today' => NewsletterSubscriber::whereDate('subscribed_at', today())->count(),
            'this_week' => NewsletterSubscriber::whereBetween('subscribed_at', [
                now()->startOfWeek(),
                now()->endOfWeek()
            ])->count(),
            'this_month' => NewsletterSubscriber::whereMonth('subscribed_at', now()->month)
                ->whereYear('subscribed_at', now()->year)
                ->count(),
        ];

        return response()->json([
            'success' => true,
            'subscribers' => $subscribers,
            'stats' => $stats,
        ]);
    }

    /**
     * Supprimer un abonné
     */
    public function destroy(Request $request, NewsletterSubscriber $subscriber)
    {
        if (!$request->user()->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé',
            ], 403);
        }

        $email = $subscriber->email;
        $subscriber->delete();

        Log::info('🗑️ Abonné newsletter supprimé', [
            'email' => $email,
            'admin' => $request->user()->name,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Abonné supprimé',
        ]);
    }

    /**
     * Exporter la liste des emails (CSV)
     */
    public function export(Request $request)
    {
        if (!$request->user()->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé',
            ], 403);
        }

        $subscribers = NewsletterSubscriber::active()
            ->orderBy('email')
            ->get();

        $emails = $subscribers->pluck('email')->toArray();

        return response()->json([
            'success' => true,
            'emails' => $emails,
            'count' => count($emails),
        ]);
    }
}