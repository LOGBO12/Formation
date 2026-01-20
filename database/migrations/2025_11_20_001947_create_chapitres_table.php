<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chapitres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained()->onDelete('cascade');
            $table->string('titre');
            $table->text('description')->nullable();
            $table->enum('type', ['video', 'pdf', 'texte', 'quiz'])->default('texte');
            $table->text('contenu')->nullable()->comment('URL video, chemin fichier, ou texte');
            $table->integer('duree')->nullable()->comment('Durée en minutes');
            $table->integer('ordre')->default(0);
            $table->boolean('is_preview')->default(false)->comment('Aperçu gratuit');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chapitres');
    }
};