<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// ============ MESSAGE REACTION ============
class MessageReaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'message_id',
        'user_id',
        'reaction',
    ];

    public function message()
    {
        return $this->belongsTo(MessageCommunaute::class, 'message_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Emojis disponibles
    public static function availableReactions()
    {
        return [
            'like' => '👍',
            'love' => '❤️',
            'laugh' => '😂',
            'wow' => '😮',
            'sad' => '😢',
            'party' => '🎉',
            'fire' => '🔥',
            'clap' => '👏',
        ];
    }
}