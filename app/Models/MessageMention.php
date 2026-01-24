<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// ============ MESSAGE MENTION ============
class MessageMention extends Model
{
    use HasFactory;

    protected $fillable = [
        'message_id',
        'mentioned_user_id',
        'is_read',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];

    public function message()
    {
        return $this->belongsTo(MessageCommunaute::class, 'message_id');
    }

    public function mentionedUser()
    {
        return $this->belongsTo(User::class, 'mentioned_user_id');
    }

    public function markAsRead()
    {
        $this->update(['is_read' => true]);
    }

    // Scope pour les mentions non lues
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    // Scope pour les mentions d'un utilisateur
    public function scopeForUser($query, $userId)
    {
        return $query->where('mentioned_user_id', $userId);
    }
}