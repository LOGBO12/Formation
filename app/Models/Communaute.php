<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Communaute extends Model
{
    use HasFactory;

    protected $fillable = [
        'formation_id',
        'nom',
        'description',
    ];

    // Relations
    public function formation()
    {
        return $this->belongsTo(Formation::class);
    }

    public function membres()
    {
        return $this->belongsToMany(User::class, 'communaute_membres')
            ->withPivot('role', 'is_muted', 'joined_at')
            ->withTimestamps();
    }

    public function messages()
    {
        return $this->hasMany(MessageCommunaute::class)->orderBy('created_at', 'desc');
    }

    // Méthodes utilitaires
    public function ajouterMembre($userId, $role = 'membre')
    {
        if (!$this->membres()->where('user_id', $userId)->exists()) {
            $this->membres()->attach($userId, [
                'role' => $role,
                'joined_at' => now(),
            ]);
        }
    }

    public function retirerMembre($userId)
    {
        $this->membres()->detach($userId);
    }

    public function muterMembre($userId)
    {
        $this->membres()->updateExistingPivot($userId, ['is_muted' => true]);
    }

    public function demuterMembre($userId)
    {
        $this->membres()->updateExistingPivot($userId, ['is_muted' => false]);
    }

    public function estAdmin($userId)
    {
        $membre = $this->membres()->where('user_id', $userId)->first();
        return $membre && $membre->pivot->role === 'admin';
    }

    public function estMute($userId)
    {
        $membre = $this->membres()->where('user_id', $userId)->first();
        return $membre && $membre->pivot->is_muted;
    }

    public function totalMembres()
    {
        return $this->membres()->count();
    }
}