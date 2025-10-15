<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

        // Livreur
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

    /**
     * Attributs virtuels pour concaténer les noms complets
     */
    protected $appends = ['nom_complet_proprietaire', 'nom_complet_livreur'];

    public function getNomCompletProprietaireAttribute(): ?string
    {
        return trim("{$this->prenom_proprietaire} {$this->nom_proprietaire}") ?: null;
    }

    public function getNomCompletLivreurAttribute(): ?string
    {
        return trim("{$this->prenom_livreur} {$this->nom_livreur}") ?: null;
    }
}
