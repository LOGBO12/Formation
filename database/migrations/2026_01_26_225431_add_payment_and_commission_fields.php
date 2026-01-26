<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Numéro de téléphone pour recevoir les paiements (formateurs)
            $table->string('payment_phone')->nullable()->after('email');
            $table->string('payment_phone_country')->default('bj')->after('payment_phone'); // Code pays (bj, tg, ci, sn)
        });

        Schema::table('formations', function (Blueprint $table) {
            // Commission admin sur cette formation (par défaut 10%)
            $table->decimal('commission_admin', 5, 2)->default(10.00)->after('prix')
                ->comment('Commission admin en pourcentage (5-10%)');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['payment_phone', 'payment_phone_country']);
        });

        Schema::table('formations', function (Blueprint $table) {
            $table->dropColumn('commission_admin');
        });
    }
};