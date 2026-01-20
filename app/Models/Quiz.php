<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Quiz extends Model
{
    use HasFactory;

    protected $table = 'quiz';

    protected $fillable = [
        'chapitre_id',
        'titre',
        'description',
        'duree_minutes',
        'note_passage',
    ];

    protected $casts = [
        'duree_minutes' => 'integer',
        'note_passage' => 'integer',
    ];

    // Relations
    public function chapitre()
    {
        return $this->belongsTo(Chapitre::class);
    }

    public function questions()
    {
        return $this->hasMany(QuizQuestion::class)->orderBy('ordre');
    }

    public function resultats()
    {
        return $this->hasMany(QuizResultat::class);
    }

    // Méthodes utilitaires
    public function totalQuestions()
    {
        return $this->questions()->count();
    }

    public function scoreMaximum()
    {
        return $this->questions()->sum('points');
    }

    public function resultatPour($userId)
    {
        return $this->resultats()->where('user_id', $userId)->latest()->first();
    }
}