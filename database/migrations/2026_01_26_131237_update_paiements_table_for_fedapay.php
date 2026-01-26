<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paiements', function (Blueprint $table) {

            // Colonnes spécifiques FedaPay
            $table->string('fedapay_token')->nullable()->after('fedapay_transaction_id');
            $table->string('fedapay_status')->nullable()->after('fedapay_token');
            $table->text('fedapay_response')->nullable()->after('fedapay_status');

            // Infos client
            $table->string('phone_number')->nullable()->after('user_id');
            $table->string('customer_email')->nullable()->after('phone_number');
            $table->string('customer_name')->nullable()->after('customer_email');

            // Transformer metadata en JSON
            $table->json('metadata')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            $table->dropColumn([
                'fedapay_token',
                'fedapay_status',
                'fedapay_response',
                'phone_number',
                'customer_email',
                'customer_name',
            ]);
        });
    }
};
