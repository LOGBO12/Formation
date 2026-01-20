<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Table communautes
        Schema::create('communautes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('formation_id')->constrained()->onDelete('cascade');
            $table->string('nom');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Table membres de communauté
        Schema::create('communaute_membres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('communaute_id')->constrained('communautes')->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('role', ['admin', 'membre'])->default('membre');
            $table->boolean('is_muted')->default(false)->comment('Réduit au silence');
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamps();

            $table->unique(['communaute_id', 'user_id']);
        });

        // Table messages
        Schema::create('messages_communaute', function (Blueprint $table) {
            $table->id();
            $table->foreignId('communaute_id')->constrained('communautes')->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->text('message');
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_announcement')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages_communaute');
        Schema::dropIfExists('communaute_membres');
        Schema::dropIfExists('communautes');
    }
};