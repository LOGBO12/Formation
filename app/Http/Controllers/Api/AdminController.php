<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Formation;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function users(Request $request)
    {
        if (!$request->user()->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => 'Non autorisé'], 403);
        }

        $users = User::with(['profile', 'domaines'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'users' => $users,
        ]);
    }

    public function toggleUserStatus(Request $request, User $user)
    {
        if (!$request->user()->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => 'Non autorisé'], 403);
        }

        $user->update(['is_active' => !$user->is_active]);

        return response()->json([
            'success' => true,
            'message' => $user->is_active ? 'Utilisateur activé' : 'Utilisateur désactivé',
            'user' => $user,
        ]);
    }

    public function allFormations(Request $request)
    {
        if (!$request->user()->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => 'Non autorisé'], 403);
        }

        $formations = Formation::with(['domaine', 'formateur'])
            ->withCount('inscriptions')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'formations' => $formations,
        ]);
    }
}