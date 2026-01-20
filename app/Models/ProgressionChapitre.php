<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProgressionChapitre extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'chapitre_id',
        'is_completed',
        'date_completion',
    ];

    protected $casts = [
        'is_completed' => 'boolean',
        'date_completion' => 'datetime',
    ];

    // Relations
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function chapitre()
    {
        return $this->belongsTo(Chapitre::class);
    }

    // Méthodes utilitaires
    public function marquerComplete()
    {
        $this->update([
            'is_completed' => true,
            'date_completion' => now(),
        ]);
    }
}