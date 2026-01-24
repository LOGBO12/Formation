<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Formation;
use App\Models\Communaute;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FormateurController extends Controller
{
    /**
     * Mes communautés (formateur)
     */
    public function mesCommunautes(Request $request)
    {
        try {
            $formateurId = $request->user()->id;
            
            \Log::info('🔵 Chargement communautés formateur:', ['id' => $formateurId]);

            // Récupérer toutes les formations du formateur
            $formations = Formation::where('formateur_id', $formateurId)
                ->with('communaute')
                ->get();

            \Log::info('📋 Formations trouvées:', ['count' => $formations->count()]);

            $communautes = [];

            foreach ($formations as $formation) {
                if ($formation->communaute) {
                    // Compter les membres et messages
                    $totalMembres = DB::table('communaute_membres')
                        ->where('communaute_id', $formation->communaute->id)
                        ->count();
                    
                    $totalMessages = DB::table('messages_communaute')
                        ->where('communaute_id', $formation->communaute->id)
                        ->whereNull('deleted_at')
                        ->count();

                    $communautes[] = [
                        'id' => $formation->communaute->id,
                        'nom' => $formation->communaute->nom,
                        'description' => $formation->communaute->description,
                        'formation' => [
                            'id' => $formation->id,
                            'titre' => $formation->titre,
                            'domaine' => $formation->domaine->name ?? 'N/A',
                        ],
                        'total_membres' => $totalMembres,
                        'total_messages' => $totalMessages,
                        'mon_role' => 'admin', // Le formateur est toujours admin
                        'created_at' => $formation->communaute->created_at,
                    ];

                    \Log::info('✅ Communauté ajoutée:', [
                        'id' => $formation->communaute->id,
                        'formation' => $formation->titre
                    ]);
                }
            }

            \Log::info('🎯 Total communautés:', ['count' => count($communautes)]);

            return response()->json([
                'success' => true,
                'communautes' => $communautes,
            ]);

        } catch (\Exception $e) {
            \Log::error('❌ Erreur mesCommunautes formateur:', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des communautés',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}