<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('formateur_withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('formateur_id')->constrained('users')->onDelete('cascade');
            
            // Informations de retrait
            $table->decimal('montant_demande', 10, 2)->comment('Montant que le formateur veut retirer');
            $table->decimal('solde_disponible', 10, 2)->comment('Solde disponible au moment de la demande');
            $table->string('phone_number')->comment('Numéro pour recevoir l\'argent');
            $table->string('phone_country')->default('bj');
            
            // Statut et traitement
            $table->enum('statut', ['pending', 'approved', 'rejected', 'completed', 'failed'])
                  ->default('pending');
            $table->text('admin_notes')->nullable()->comment('Notes de l\'admin');
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            
            // FedaPay (pour le payout)
            $table->string('fedapay_payout_id')->nullable();
            $table->json('fedapay_response')->nullable();
            
            $table->timestamps();
            
            $table->index(['formateur_id', 'statut']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formateur_withdrawals');
    }
};