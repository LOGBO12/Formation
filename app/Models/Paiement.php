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
        'montant',
        'statut',
        'fedapay_transaction_id',
        'methode_paiement',
        'metadata',
        'date_paiement',
    ];

    protected $casts = [
        'montant' => 'decimal:2',
        'date_paiement' => 'datetime',
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
    public function scopeCompletes($query)
    {
        return $query->where('statut', 'complete');
    }

    public function scopeEnAttente($query)
    {
        return $query->where('statut', 'en_attente');
    }

    public function scopeEchoues($query)
    {
        return $query->where('statut', 'echoue');
    }

    // Méthodes utilitaires
    public function estComplete()
    {
        return $this->statut === 'complete';
    }

    public function estEnAttente()
    {
        return $this->statut === 'en_attente';
    }

    public function marquerComplete()
    {
        $this->update([
            'statut' => 'complete',
            'date_paiement' => now(),
        ]);
    }

    public function marquerEchoue()
    {
        $this->update(['statut' => 'echoue']);
    }
}