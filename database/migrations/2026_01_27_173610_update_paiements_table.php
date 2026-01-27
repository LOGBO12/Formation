<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paiements', function (Blueprint $table) {

            if (Schema::hasColumn('paiements', 'fedapay_transaction_id')) {
                $table->renameColumn('fedapay_transaction_id', 'transaction_id');
            }

            if (!Schema::hasColumn('paiements', 'payment_url')) {
                $table->text('payment_url')->nullable()->after('transaction_id');
            }

            if (!Schema::hasColumn('paiements', 'date_paiement')) {
                $table->timestamp('date_paiement')->nullable()->after('fedapay_response');
            }

            if (Schema::hasColumn('paiements', 'statut')) {
                $table->string('statut', 20)->default('en_attente')->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('paiements', function (Blueprint $table) {

            if (Schema::hasColumn('paiements', 'transaction_id')) {
                $table->renameColumn('transaction_id', 'fedapay_transaction_id');
            }

            if (Schema::hasColumn('paiements', 'payment_url')) {
                $table->dropColumn('payment_url');
            }

            if (Schema::hasColumn('paiements', 'date_paiement')) {
                $table->dropColumn('date_paiement');
            }

            $table->string('statut', 20)->change();
        });
    }
};
