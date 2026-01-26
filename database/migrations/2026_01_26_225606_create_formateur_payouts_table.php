<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('formateur_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paiement_id')->constrained('paiements')->onDelete('cascade');
            $table->foreignId('formateur_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('formation_id')->constrained('formations')->onDelete('cascade');
            
            // Montants
            $table->decimal('montant_total', 10, 2)->comment('Montant payé par l\'apprenant');
            $table->decimal('commission_admin', 10, 2)->comment('Commission plateforme (5-10%)');
            $table->decimal('montant_formateur', 10, 2)->comment('Montant reversé au formateur');
            
            // FedaPay
            $table->string('fedapay_payout_id')->nullable();
            $table->string('statut')->default('pending'); // pending, sent, completed, failed
            $table->text('fedapay_response')->nullable();
            $table->timestamp('date_payout')->nullable();
            
            $table->timestamps();
            
            $table->index(['formateur_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formateur_payouts');
    }
};