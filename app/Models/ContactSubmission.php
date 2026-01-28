<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContactSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'subject',
        'message',
        'status',
        'ip_address',
        'user_agent',
        'admin_notes',
        'responded_at',
        'responded_by',
    ];

    protected $casts = [
        'responded_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relation avec l'admin qui a répondu
     */
    public function respondedBy()
    {
        return $this->belongsTo(User::class, 'responded_by');
    }

    /**
     * Scopes
     */
    public function scopeNew($query)
    {
        return $query->where('status', 'new');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }

    /**
     * Marquer comme en cours
     */
    public function markAsInProgress()
    {
        $this->update(['status' => 'in_progress']);
    }

    /**
     * Marquer comme résolu
     */
    public function markAsResolved($adminId, $notes = null)
    {
        $this->update([
            'status' => 'resolved',
            'responded_at' => now(),
            'responded_by' => $adminId,
            'admin_notes' => $notes,
        ]);
    }

    /**
     * Obtenir le badge de statut
     */
    public function getStatusBadgeAttribute()
    {
        $badges = [
            'new' => ['color' => 'primary', 'label' => 'Nouveau'],
            'in_progress' => ['color' => 'warning', 'label' => 'En cours'],
            'resolved' => ['color' => 'success', 'label' => 'Résolu'],
        ];

        return $badges[$this->status] ?? ['color' => 'secondary', 'label' => 'Inconnu'];
    }
}