<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Paiement extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'formation_id',
        'transaction_id',
        'montant',
        'statut',
        'methode_paiement',
        'payment_url',
        'metadata',
        'fedapay_response',
    ];

    /**
     * ⚠️ TRÈS IMPORTANT : Caster metadata et fedapay_response en array/json
     */
    protected $casts = [
        'montant' => 'decimal:2',
        'metadata' => 'array',           // ✅ Convertit automatiquement JSON ↔ Array
        'fedapay_response' => 'array',   // ✅ Convertit automatiquement JSON ↔ Array
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relations
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function formation()
    {
        return $this->belongsTo(Formation::class);
    }

    /**
     * Scopes
     */
    public function scopeComplete($query)
    {
        return $query->where('statut', 'complete');
    }

    public function scopeEnAttente($query)
    {
        return $query->where('statut', 'en_attente');
    }

    public function scopeEchec($query)
    {
        return $query->where('statut', 'echec');
    }

    /**
     * Accesseurs
     */
    public function getMontantFormateAttribute()
    {
        return number_format($this->montant, 0, ',', ' ') . ' FCFA';
    }

    public function getStatutBadgeAttribute()
    {
        $badges = [
            'en_attente' => 'warning',
            'complete' => 'success',
            'echec' => 'danger',
            'annule' => 'secondary',
        ];

        return $badges[$this->statut] ?? 'secondary';
    }

    public function getStatutLabelAttribute()
    {
        $labels = [
            'en_attente' => 'En attente',
            'complete' => 'Complété',
            'echec' => 'Échoué',
            'annule' => 'Annulé',
        ];

        return $labels[$this->statut] ?? $this->statut;
    }

    /**
     * Méthodes helper
     */
    public function isComplete()
    {
        return $this->statut === 'complete';
    }

    public function isEnAttente()
    {
        return $this->statut === 'en_attente';
    }

    public function isEchec()
    {
        return $this->statut === 'echec';
    }
}