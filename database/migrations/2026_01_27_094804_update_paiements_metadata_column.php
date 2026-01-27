<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            // Changer le type de metadata et fedapay_response en JSON
            // Si vous avez des erreurs, utilisez TEXT au lieu de JSON
            $table->json('metadata')->nullable()->change();
            $table->json('fedapay_response')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            $table->text('metadata')->nullable()->change();
            $table->text('fedapay_response')->nullable()->change();
        });
    }
};