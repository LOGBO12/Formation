<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MessageCommunaute extends Model
{
    use HasFactory;

    protected $table = 'messages_communaute';

    protected $fillable = [
        'communaute_id',
        'user_id',
        'message',
        'is_pinned',
        'is_announcement',
    ];

    protected $casts = [
        'is_pinned' => 'boolean',
        'is_announcement' => 'boolean',
    ];

    // Relations
    public function communaute()
    {
        return $this->belongsTo(Communaute::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopePinned($query)
    {
        return $query->where('is_pinned', true);
    }

    public function scopeAnnouncements($query)
    {
        return $query->where('is_announcement', true);
    }

    // Méthodes utilitaires
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
}