<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormateurWithdrawal extends Model
{
    use HasFactory;

    protected $fillable = [
        'formateur_id',
        'montant_demande',
        'solde_disponible',
        'phone_number',
        'phone_country',
        'statut',
        'admin_notes',
        'processed_by',
        'processed_at',
        'fedapay_payout_id',
        'fedapay_response',
    ];

    protected $casts = [
        'montant_demande' => 'decimal:2',
        'solde_disponible' => 'decimal:2',
        'fedapay_response' => 'array',
        'processed_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    /**
     * Relations
     */
    public function formateur()
    {
        return $this->belongsTo(User::class, 'formateur_id');
    }

    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * Scopes
     */
    public function scopePending($query)
    {
        return $query->where('statut', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('statut', 'approved');
    }

    public function scopeCompleted($query)
    {
        return $query->where('statut', 'completed');
    }

    public function scopeRejected($query)
    {
        return $query->where('statut', 'rejected');
    }

    /**
     * Accesseurs
     */
    public function getStatutLabelAttribute()
    {
        $labels = [
            'pending' => 'En attente',
            'approved' => 'Approuvé',
            'rejected' => 'Rejeté',
            'completed' => 'Complété',
            'failed' => 'Échoué',
        ];
        return $labels[$this->statut] ?? $this->statut;
    }

    public function getStatutBadgeAttribute()
    {
        $badges = [
            'pending' => 'warning',
            'approved' => 'info',
            'rejected' => 'danger',
            'completed' => 'success',
            'failed' => 'danger',
        ];
        return $badges[$this->statut] ?? 'secondary';
    }

    /**
     * Méthodes
     */
    public function isPending()
    {
        return $this->statut === 'pending';
    }

    public function isApproved()
    {
        return $this->statut === 'approved';
    }

    public function isCompleted()
    {
        return $this->statut === 'completed';
    }

    public function approve($adminId, $notes = null)
    {
        $this->update([
            'statut' => 'approved',
            'processed_by' => $adminId,
            'processed_at' => now(),
            'admin_notes' => $notes,
        ]);
    }

    public function reject($adminId, $notes)
    {
        $this->update([
            'statut' => 'rejected',
            'processed_by' => $adminId,
            'processed_at' => now(),
            'admin_notes' => $notes,
        ]);
    }

    public function markAsCompleted($fedapayPayoutId = null, $response = null)
    {
        $this->update([
            'statut' => 'completed',
            'fedapay_payout_id' => $fedapayPayoutId,
            'fedapay_response' => $response,
        ]);
    }

    public function markAsFailed($response = null)
    {
        $this->update([
            'statut' => 'failed',
            'fedapay_response' => $response,
        ]);
    }
}