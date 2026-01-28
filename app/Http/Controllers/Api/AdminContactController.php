<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContactSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminContactController extends Controller
{
    /**
     * Liste des soumissions de contact
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

        $query = ContactSubmission::with('respondedBy:id,name')
            ->orderBy('created_at', 'desc');

        // Filtre par statut
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        // Recherche
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('subject', 'like', "%{$search}%");
            });
        }

        $submissions = $query->paginate(20);

        return response()->json([
            'success' => true,
            'submissions' => $submissions,
        ]);
    }

    /**
     * Afficher une soumission spécifique
     */
    public function show(Request $request, ContactSubmission $submission)
    {
        if (!$request->user()->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé',
            ], 403);
        }

        $submission->load('respondedBy:id,name');

        return response()->json([
            'success' => true,
            'submission' => $submission,
        ]);
    }

    /**
     * Changer le statut d'une soumission
     */
    public function updateStatus(Request $request, ContactSubmission $submission)
    {
        if (!$request->user()->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé',
            ], 403);
        }

        $request->validate([
            'status' => 'required|in:new,in_progress,resolved',
        ]);

        $submission->update(['status' => $request->status]);

        Log::info('📝 Statut contact mis à jour', [
            'id' => $submission->id,
            'old_status' => $submission->getOriginal('status'),
            'new_status' => $request->status,
            'admin' => $request->user()->name,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Statut mis à jour',
            'submission' => $submission->fresh(),
        ]);
    }

    /**
     * Marquer comme résolu et ajouter des notes
     */
    public function respond(Request $request, ContactSubmission $submission)
    {
        if (!$request->user()->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé',
            ], 403);
        }

        $request->validate([
            'admin_notes' => 'nullable|string|max:2000',
        ]);

        $submission->markAsResolved(
            $request->user()->id,
            $request->admin_notes
        );

        Log::info('✅ Contact résolu', [
            'id' => $submission->id,
            'admin' => $request->user()->name,
        ]);

        // TODO: Envoyer email de réponse au contact
        // Mail::to($submission->email)->send(new ContactResponseMail($submission));

        return response()->json([
            'success' => true,
            'message' => 'Contact marqué comme résolu',
            'submission' => $submission->fresh(['respondedBy']),
        ]);
    }

    /**
     * Supprimer une soumission
     */
    public function destroy(Request $request, ContactSubmission $submission)
    {
        if (!$request->user()->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé',
            ], 403);
        }

        $submission->delete();

        Log::info('🗑️ Contact supprimé', [
            'id' => $submission->id,
            'admin' => $request->user()->name,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Soumission supprimée',
        ]);
    }
}