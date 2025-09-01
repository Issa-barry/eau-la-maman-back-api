<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CommandeLigne extends Model
{
    use HasFactory;

    protected $fillable = [
    'commande_id',
    'produit_id',
    'prix_vente',
    'quantite_commandee',
    'quantite_restante',
];


    public function commande()
    {
        return $this->belongsTo(Commande::class);
    }

    public function produit()
    {
        return $this->belongsTo(Produit::class);
    }

     public function livraisonLignes()
    {
        return $this->hasMany(LivraisonLigne::class, 'commande_ligne_id');
    }
}
