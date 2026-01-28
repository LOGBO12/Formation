<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * ✅ Migration pour vérifier et corriger la table notifications
     */
    public function up(): void
    {
        // Si la table existe déjà, on ne la recrée pas
        if (Schema::hasTable('notifications')) {
            // Vérifier et ajouter les colonnes manquantes si nécessaire
            Schema::table('notifications', function (Blueprint $table) {
                if (!Schema::hasColumn('notifications', 'lu')) {
                    $table->boolean('lu')->default(false)->after('data');
                }
                
                if (!Schema::hasColumn('notifications', 'lu_at')) {
                    $table->timestamp('lu_at')->nullable()->after('lu');
                }
            });
            
            return;
        }

        // Créer la table si elle n'existe pas
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('type'); // 'nouvelle_formation', 'nouveau_message', 'paiement_recu', etc.
            $table->string('titre');
            $table->text('message');
            $table->string('lien')->nullable(); // URL vers la ressource
            $table->json('data')->nullable(); // Données supplémentaires
            $table->boolean('lu')->default(false);
            $table->timestamp('lu_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'lu']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};