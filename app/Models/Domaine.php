<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Domaine extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Générer automatiquement le slug
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($domaine) {
            if (empty($domaine->slug)) {
                $domaine->slug = Str::slug($domaine->name);
            }
        });

        static::updating(function ($domaine) {
            if ($domaine->isDirty('name') && empty($domaine->slug)) {
                $domaine->slug = Str::slug($domaine->name);
            }
        });
    }

    // Relation avec les utilisateurs
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_domaines');
    }

    // Scope pour les domaines actifs
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}