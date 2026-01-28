<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormateurPayout extends Model
{
    use HasFactory;

    protected $fillable = [
        'formateur_id',
        'paiement_id',
        'formation_id',
        'montant_total',
        'commission_admin',
        'montant_formateur',
        'statut',
        'fedapay_payout_id',
        'fedapay_response',
        'date_payout',
    ];

    protected $casts = [
        'montant_total' => 'decimal:2',
        'commission_admin' => 'decimal:2',
        'montant_formateur' => 'decimal:2',
        'fedapay_response' => 'array',
        'date_payout' => 'datetime',
    ];

    /**
     * Relations
     */
    public function formateur()
    {
        return $this->belongsTo(User::class, 'formateur_id');
    }

    public function paiement()
    {
        return $this->belongsTo(Paiement::class);
    }

    public function formation()
    {
        return $this->belongsTo(Formation::class);
    }

    /**
     * Scopes
     */
    public function scopePending($query)
    {
        return $query->where('statut', 'pending');
    }

    public function scopeSent($query)
    {
        return $query->where('statut', 'sent');
    }

    public function scopeCompleted($query)
    {
        return $query->where('statut', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('statut', 'failed');
    }

    /**
     * Accesseurs
     */
    public function getMontantFormateAttribute()
    {
        return number_format($this->montant_formateur, 0, ',', ' ') . ' FCFA';
    }

    public function getStatutBadgeAttribute()
    {
        $badges = [
            'pending' => 'warning',
            'sent' => 'info',
            'completed' => 'success',
            'failed' => 'danger',
        ];

        return $badges[$this->statut] ?? 'secondary';
    }

    /**
     * Méthodes helper
     */
    public function isPending()
    {
        return $this->statut === 'pending';
    }

    public function isSent()
    {
        return $this->statut === 'sent';
    }

    public function isCompleted()
    {
        return $this->statut === 'completed';
    }

    public function isFailed()
    {
        return $this->statut === 'failed';
    }
}