<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Table quiz
        Schema::create('quiz', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chapitre_id')->constrained('chapitres')->onDelete('cascade');
            $table->string('titre');
            $table->text('description')->nullable();
            $table->integer('duree_minutes')->nullable();
            $table->integer('note_passage')->default(50)->comment('Pourcentage minimum pour réussir');
            $table->timestamps();
        });

        // Table questions
        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained('quiz')->onDelete('cascade');
            $table->text('question');
            $table->enum('type', ['choix_multiple', 'vrai_faux'])->default('choix_multiple');
            $table->integer('points')->default(1);
            $table->integer('ordre')->default(0);
            $table->timestamps();
        });

        // Table options de réponse
        Schema::create('quiz_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('quiz_questions')->onDelete('cascade');
            $table->text('option_texte');
            $table->boolean('is_correct')->default(false);
            $table->timestamps();
        });

        // Table résultats
        Schema::create('quiz_resultats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('quiz_id')->constrained('quiz')->onDelete('cascade');
            $table->integer('score')->comment('Points obtenus');
            $table->integer('score_max')->comment('Points maximum');
            $table->decimal('pourcentage', 5, 2);
            $table->enum('statut', ['reussi', 'echoue'])->default('echoue');
            $table->integer('temps_ecoule')->nullable()->comment('En secondes');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_resultats');
        Schema::dropIfExists('quiz_options');
        Schema::dropIfExists('quiz_questions');
        Schema::dropIfExists('quiz');
    }
};