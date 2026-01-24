<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Ajouter les colonnes manquantes
        Schema::table('messages_communaute', function (Blueprint $table) {
            if (!Schema::hasColumn('messages_communaute', 'type')) {
                $table->enum('type', ['text', 'image', 'video', 'audio', 'pdf', 'file'])
                      ->default('text')
                      ->after('message');
            }

            if (!Schema::hasColumn('messages_communaute', 'attachments')) {
                $table->json('attachments')->nullable()->after('type')
                      ->comment('URLs des fichiers uploadés');
            }

            if (!Schema::hasColumn('messages_communaute', 'attachments_meta')) {
                $table->json('attachments_meta')->nullable()->after('attachments')
                      ->comment('Tailles, types MIME, noms originaux');
            }

            if (!Schema::hasColumn('messages_communaute', 'parent_message_id')) {
                $table->foreignId('parent_message_id')->nullable()
                      ->after('user_id')
                      ->constrained('messages_communaute')
                      ->onDelete('cascade')
                      ->comment('Pour les réponses/threads');
            }

            if (!Schema::hasColumn('messages_communaute', 'is_edited')) {
                $table->boolean('is_edited')->default(false)->after('is_announcement');
            }

            if (!Schema::hasColumn('messages_communaute', 'edited_at')) {
                $table->timestamp('edited_at')->nullable()->after('is_edited');
            }

            if (!Schema::hasColumn('messages_communaute', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        // Créer les nouvelles tables
        if (!Schema::hasTable('message_reactions')) {
            Schema::create('message_reactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('message_id')
                      ->constrained('messages_communaute')
                      ->onDelete('cascade');
                $table->foreignId('user_id')
                      ->constrained()
                      ->onDelete('cascade');
                $table->string('reaction', 50);
                $table->timestamps();
                
                $table->unique(['message_id', 'user_id', 'reaction']);
            });
        }

        if (!Schema::hasTable('message_mentions')) {
            Schema::create('message_mentions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('message_id')
                      ->constrained('messages_communaute')
                      ->onDelete('cascade');
                $table->foreignId('mentioned_user_id')
                      ->constrained('users')
                      ->onDelete('cascade');
                $table->boolean('is_read')->default(false);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('message_views')) {
            Schema::create('message_views', function (Blueprint $table) {
                $table->id();
                $table->foreignId('message_id')
                      ->constrained('messages_communaute')
                      ->onDelete('cascade');
                $table->foreignId('user_id')
                      ->constrained()
                      ->onDelete('cascade');
                $table->timestamp('viewed_at')->useCurrent();
                
                $table->unique(['message_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('message_views');
        Schema::dropIfExists('message_mentions');
        Schema::dropIfExists('message_reactions');
        
        Schema::table('messages_communaute', function (Blueprint $table) {
            $columns = [
                'deleted_at',
                'edited_at',
                'is_edited',
                'parent_message_id',
                'attachments_meta',
                'attachments',
                'type'
            ];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('messages_communaute', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};