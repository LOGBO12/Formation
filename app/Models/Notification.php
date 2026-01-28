<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'titre',
        'message',
        'lien',
        'data',
        'lu',
        'lu_at',
    ];

    protected $casts = [
        'data' => 'array',
        'lu' => 'boolean',
        'lu_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = ['temps_ecoule'];

    /**
     * Relation avec l'utilisateur
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Marquer comme lu
     */
    public function marquerCommeLu()
    {
        $this->update([
            'lu' => true,
            'lu_at' => now(),
        ]);
    }

    /**
     * Scope pour les notifications non lues
     */
    public function scopeNonLues($query)
    {
        return $query->where('lu', false);
    }

    /**
     * Scope pour les notifications lues
     */
    public function scopeLues($query)
    {
        return $query->where('lu', true);
    }

    /**
     * Scope pour un type spécifique
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Temps écoulé depuis la création
     */
    public function getTempsEcouleAttribute()
    {
        return $this->created_at->diffForHumans();
    }

    /**
     * Icône selon le type
     */
    public function getIconeAttribute()
    {
        $icons = [
            'nouvelle_formation' => '📚',
            'nouveau_message' => '💬',
            'paiement_recu' => '💰',
            'inscription_validee' => '✅',
            'certificat_obtenu' => '🎓',
            'nouveau_cours' => '📖',
            'reponse_commentaire' => '💭',
            'nouveau_membre' => '👤',
        ];

        return $icons[$this->type] ?? '🔔';
    }

    /**
     * Couleur selon le type
     */
    public function getCouleurAttribute()
    {
        $colors = [
            'nouvelle_formation' => 'primary',
            'nouveau_message' => 'success',
            'paiement_recu' => 'warning',
            'inscription_validee' => 'success',
            'certificat_obtenu' => 'info',
            'nouveau_cours' => 'primary',
            'reponse_commentaire' => 'secondary',
            'nouveau_membre' => 'info',
        ];

        return $colors[$this->type] ?? 'secondary';
    }
}