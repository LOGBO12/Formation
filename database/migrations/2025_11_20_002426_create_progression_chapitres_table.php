<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('progression_chapitres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('chapitre_id')->constrained('chapitres')->onDelete('cascade');
            $table->boolean('is_completed')->default(false);
            $table->timestamp('date_completion')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'chapitre_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('progression_chapitres');
    }
};