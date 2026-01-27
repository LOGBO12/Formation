<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            // Changer ENUM en VARCHAR pour plus de flexibilité
            $table->string('statut', 20)->default('en_attente')->change();
        });
    }

    public function down(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            $table->enum('statut', ['en_attente', 'complete', 'echec', 'annule'])
                ->default('en_attente')
                ->change();
        });
    }
};