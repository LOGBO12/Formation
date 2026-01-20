<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Formation extends Model
{
    use HasFactory;

    protected $fillable = [
        'formateur_id',
        'domaine_id',
        'titre',
        'slug',
        'description',
        'image',
        'prix',
        'is_free',
        'statut',
        'duree_estimee',
        'lien_public',
    ];

    protected $casts = [
        'is_free' => 'boolean',
        'prix' => 'decimal:2',
    ];
protected static function boot()
{
    parent::boot();

    static::creating(function ($formation) {
        if (empty($formation->slug)) {
            $baseSlug = Str::slug($formation->titre);
            $slug = $baseSlug;
            $count = 1;
            
            while (self::where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $count;
                $count++;
            }
            
            $formation->slug = $slug;
        }
        if (empty($formation->lien_public)) {
            $formation->lien_public = Str::random(10);
        }
    });
}
    // Relations
    public function formateur()
    {
        return $this->belongsTo(User::class, 'formateur_id');
    }

    public function domaine()
    {
        return $this->belongsTo(Domaine::class);
    }

    public function modules()
    {
        return $this->hasMany(Module::class)->orderBy('ordre');
    }

    public function inscriptions()
    {
        return $this->hasMany(Inscription::class);
    }

    public function apprenants()
    {
        return $this->belongsToMany(User::class, 'inscriptions')
            ->withPivot('statut', 'progres', 'is_blocked')
            ->withTimestamps();
    }

    public function communaute()
    {
        return $this->hasOne(Communaute::class);
    }

    public function paiements()
    {
        return $this->hasMany(Paiement::class);
    }

    // Scopes
    public function scopePubliees($query)
    {
        return $query->where('statut', 'publie');
    }

    public function scopeBrouillons($query)
    {
        return $query->where('statut', 'brouillon');
    }

    public function scopeArchivees($query)
    {
        return $query->where('statut', 'archive');
    }

    // Méthodes utilitaires
    public function isPubliee()
    {
        return $this->statut === 'publie';
    }

    public function totalApprenants()
    {
        return $this->inscriptions()->whereIn('statut', ['active', 'approuvee'])->count();
    }

    public function totalRevenus()
    {
        return $this->paiements()->where('statut', 'complete')->sum('montant');
    }
}