<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MessageCommunaute extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'messages_communaute';

    protected $fillable = [
        'communaute_id',
        'user_id',
        'parent_message_id',
        'message',
        'type',
        'attachments',
        'attachments_meta',
        'is_pinned',
        'is_announcement',
        'is_edited',
        'edited_at',
    ];

    protected $casts = [
        'is_pinned' => 'boolean',
        'is_announcement' => 'boolean',
        'is_edited' => 'boolean',
        'attachments' => 'array',
        'attachments_meta' => 'array',
        'edited_at' => 'datetime',
    ];

    /*protected $with = ['user', 'reactions', 'replies'];
    protected $withCount = ['reactions', 'replies', 'views'];
*/
    // ============ RELATIONS ============

    public function communaute()
    {
        return $this->belongsTo(Communaute::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Message parent (pour les threads)
    public function parent()
    {
        return $this->belongsTo(MessageCommunaute::class, 'parent_message_id');
    }

    // Réponses au message
    public function replies()
    {
        return $this->hasMany(MessageCommunaute::class, 'parent_message_id')
                    ->with('user')
                    ->latest();
    }

    // Réactions
    public function reactions()
    {
        return $this->hasMany(MessageReaction::class, 'message_id');
    }

    // Mentions
    public function mentions()
    {
        return $this->hasMany(MessageMention::class, 'message_id');
    }

    // Vues (read receipts)
    public function views()
    {
        return $this->hasMany(MessageView::class, 'message_id');
    }

    // ============ SCOPES ============

    public function scopePinned($query)
    {
        return $query->where('is_pinned', true);
    }

    public function scopeAnnouncements($query)
    {
        return $query->where('is_announcement', true);
    }

    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_message_id');
    }

    public function scopeWithMedia($query)
    {
        return $query->whereIn('type', ['image', 'video', 'audio', 'pdf', 'file']);
    }

    // ============ MÉTHODES UTILITAIRES ============

    public function epingler()
    {
        $this->update(['is_pinned' => true]);
    }

    public function desepingler()
    {
        $this->update(['is_pinned' => false]);
    }

    public function marquerCommeAnnonce()
    {
        $this->update(['is_announcement' => true]);
    }

    public function marquerEdite()
    {
        $this->update([
            'is_edited' => true,
            'edited_at' => now(),
        ]);
    }

    /**
     * Ajouter une réaction au message
     */
    public function addReaction($userId, $reactionType)
    {
        return $this->reactions()->updateOrCreate(
            ['user_id' => $userId, 'reaction' => $reactionType],
            ['user_id' => $userId, 'reaction' => $reactionType]
        );
    }

    /**
     * Retirer une réaction
     */
    public function removeReaction($userId, $reactionType)
    {
        return $this->reactions()
                    ->where('user_id', $userId)
                    ->where('reaction', $reactionType)
                    ->delete();
    }

    /**
     * Grouper les réactions par type
     */
    public function getGroupedReactions()
    {
        return $this->reactions()
                    ->selectRaw('reaction, count(*) as count')
                    ->groupBy('reaction')
                    ->get()
                    ->mapWithKeys(function ($item) {
                        return [$item->reaction => $item->count];
                    });
    }

    /**
     * Marquer comme vu par un utilisateur
     */
    public function markAsViewedBy($userId)
    {
        return $this->views()->firstOrCreate([
            'user_id' => $userId,
            'viewed_at' => now(),
        ]);
    }

    /**
     * Vérifier si un utilisateur a vu le message
     */
    public function isViewedBy($userId)
    {
        return $this->views()->where('user_id', $userId)->exists();
    }

    /**
     * Extraire les mentions du message
     */
    public function extractMentions()
    {
        preg_match_all('/@\[(\d+)\]/', $this->message, $matches);
        return array_unique($matches[1]);
    }

    /**
     * Créer les mentions
     */
    public function createMentions()
    {
        $mentionedUserIds = $this->extractMentions();
        
        foreach ($mentionedUserIds as $userId) {
            $this->mentions()->create([
                'mentioned_user_id' => $userId,
                'is_read' => false,
            ]);
        }
    }

    /**
     * Formater le message pour l'affichage
     */
    public function getFormattedMessageAttribute()
    {
        $message = $this->message;
        
        // Remplacer les mentions @[userId] par @Username
        $message = preg_replace_callback('/@\[(\d+)\]/', function ($matches) {
            $user = User::find($matches[1]);
            return $user ? "<span class='mention'>@{$user->name}</span>" : $matches[0];
        }, $message);
        
        return $message;
    }

    /**
     * Vérifier si le message a des médias
     */
    public function hasMedia()
    {
        return in_array($this->type, ['image', 'video', 'audio', 'pdf', 'file']);
    }

    /**
     * Obtenir les URLs des médias
     */
    public function getMediaUrls()
    {
        if (!$this->hasMedia() || !$this->attachments) {
            return [];
        }

        return array_map(function ($path) {
            return asset('storage/' . $path);
        }, $this->attachments);
    }
}