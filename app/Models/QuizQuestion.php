<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuizQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'quiz_id',
        'question',
        'type',
        'points',
        'ordre',
    ];

    protected $casts = [
        'points' => 'integer',
        'ordre' => 'integer',
    ];

    // Relations
    public function quiz()
    {
        return $this->belongsTo(Quiz::class);
    }

    public function options()
    {
        return $this->hasMany(QuizOption::class, 'question_id');
    }

    // Méthodes utilitaires
    public function bonneReponse()
    {
        return $this->options()->where('is_correct', true)->first();
    }

    public function bonnesReponses()
    {
        return $this->options()->where('is_correct', true)->get();
    }

    public function verifierReponse($optionIds)
    {
        if (!is_array($optionIds)) {
            $optionIds = [$optionIds];
        }

        $bonnesReponses = $this->bonnesReponses()->pluck('id')->toArray();
        
        sort($bonnesReponses);
        sort($optionIds);

        return $bonnesReponses === $optionIds;
    }
}