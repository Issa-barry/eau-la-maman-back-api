<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicule extends Model
{
    use HasFactory;

    protected $table = 'vehicules';

    protected $fillable = [
        'nom',
        'type',
        'immatriculation',

        // Propriétaire
        'nom_proprietaire',
        'prenom_proprietaire',
        'phone_proprietaire',

        // Livreur (historique)
        'nom_livreur',
        'prenom_livreur',
        'phone_livreur',

        // Statut
        'statut',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = ['nom_complet_proprietaire', 'nom_complet_livreur'];

    public function getNomCompletProprietaireAttribute(): ?string
    {
        return trim("{$this->prenom_proprietaire} {$this->nom_proprietaire}") ?: null;
    }

    public function getNomCompletLivreurAttribute(): ?string
    {
        return trim("{$this->prenom_livreur} {$this->nom_livreur}") ?: null;
    }

    // ⬇️ Nouvelles relations
    public function commandes(): HasMany
    {
        return $this->hasMany(Commande::class, 'vehicule_id');
    }
}
