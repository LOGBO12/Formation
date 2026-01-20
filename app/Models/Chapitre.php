<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Chapitre extends Model
{
    use HasFactory;

    protected $fillable = [
        'module_id',
        'titre',
        'description',
        'type',
        'contenu',
        'duree',
        'ordre',
        'is_preview',
    ];

    protected $casts = [
        'is_preview' => 'boolean',
        'duree' => 'integer',
        'ordre' => 'integer',
    ];

    // Relations
    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function quiz()
    {
        return $this->hasOne(Quiz::class);
    }

    public function progressions()
    {
        return $this->hasMany(ProgressionChapitre::class);
    }

    // Méthodes utilitaires
    public function isQuiz()
    {
        return $this->type === 'quiz';
    }

    public function isVideo()
    {
        return $this->type === 'video';
    }

    public function isPdf()
    {
        return $this->type === 'pdf';
    }

    public function isTexte()
    {
        return $this->type === 'texte';
    }

    public function estCompletePar($userId)
    {
        return $this->progressions()->where('user_id', $userId)->where('is_completed', true)->exists();
    }
}