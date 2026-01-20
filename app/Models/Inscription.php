<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'formation_id',
        'statut',
        'progres',
        'date_demande',
        'date_approbation',
        'date_completion',
        'is_blocked',
    ];

    protected $casts = [
        'progres' => 'decimal:2',
        'is_blocked' => 'boolean',
        'date_demande' => 'datetime',
        'date_approbation' => 'datetime',
        'date_completion' => 'datetime',
    ];

    // Relations
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function formation()
    {
        return $this->belongsTo(Formation::class);
    }

    // Scopes
    public function scopeEnAttente($query)
    {
        return $query->where('statut', 'en_attente');
    }

    public function scopeApprouvees($query)
    {
        return $query->whereIn('statut', ['approuvee', 'active']);
    }

    public function scopeActives($query)
    {
        return $query->where('statut', 'active')->where('is_blocked', false);
    }

    public function scopeBloquees($query)
    {
        return $query->where('is_blocked', true);
    }

    // Méthodes utilitaires
    public function estEnAttente()
    {
        return $this->statut === 'en_attente';
    }

    public function estApprouvee()
    {
        return in_array($this->statut, ['approuvee', 'active']);
    }

    public function estActive()
    {
        return $this->statut === 'active' && !$this->is_blocked;
    }

    public function estBloquee()
    {
        return $this->is_blocked;
    }

    public function calculerProgression()
    {
        $formation = $this->formation;
        $totalChapitres = 0;
        $chapitresCompletes = 0;

        foreach ($formation->modules as $module) {
            foreach ($module->chapitres as $chapitre) {
                $totalChapitres++;
                if ($chapitre->estCompletePar($this->user_id)) {
                    $chapitresCompletes++;
                }
            }
        }

        if ($totalChapitres === 0) {
            return 0;
        }

        $progres = ($chapitresCompletes / $totalChapitres) * 100;
        $this->update(['progres' => $progres]);

        return $progres;
    }
}