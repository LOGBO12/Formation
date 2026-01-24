<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// ============ MESSAGE VIEW ============
class MessageView extends Model
{
    use HasFactory;

    protected $fillable = [
        'message_id',
        'user_id',
        'viewed_at',
    ];

    protected $casts = [
        'viewed_at' => 'datetime',
    ];

    public $timestamps = false;

    public function message()
    {
        return $this->belongsTo(MessageCommunaute::class, 'message_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
