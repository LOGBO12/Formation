<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('formation_id')->constrained()->onDelete('cascade');
            $table->enum('statut', ['en_attente', 'approuvee', 'rejetee', 'active', 'bloquee'])->default('en_attente');
            $table->decimal('progres', 5, 2)->default(0)->comment('Pourcentage de progression');
            $table->timestamp('date_demande')->useCurrent();
            $table->timestamp('date_approbation')->nullable();
            $table->timestamp('date_completion')->nullable();
            $table->boolean('is_blocked')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'formation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inscriptions');
    }
};