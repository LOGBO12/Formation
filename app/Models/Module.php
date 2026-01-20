<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    use HasFactory;

    protected $fillable = [
        'formation_id',
        'titre',
        'description',
        'ordre',
    ];

    protected $casts = [
        'ordre' => 'integer',
    ];

    // Relations
    public function formation()
    {
        return $this->belongsTo(Formation::class);
    }

    public function chapitres()
    {
        return $this->hasMany(Chapitre::class)->orderBy('ordre');
    }

    // Méthodes utilitaires
    public function totalChapitres()
    {
        return $this->chapitres()->count();
    }

    public function dureeTotale()
    {
        return $this->chapitres()->sum('duree');
    }
}