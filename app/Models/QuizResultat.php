<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuizResultat extends Model
{
    use HasFactory;

    protected $table = 'quiz_resultats';

    protected $fillable = [
        'user_id',
        'quiz_id',
        'score',
        'score_max',
        'pourcentage',
        'statut',
        'temps_ecoule',
    ];

    protected $casts = [
        'score' => 'integer',
        'score_max' => 'integer',
        'pourcentage' => 'decimal:2',
        'temps_ecoule' => 'integer',
    ];

    // Relations
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function quiz()
    {
        return $this->belongsTo(Quiz::class);
    }

    // Méthodes utilitaires
    public function aReussi()
    {
        return $this->statut === 'reussi';
    }

    public function aEchoue()
    {
        return $this->statut === 'echoue';
    }
}