<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Produit extends Model
{
    protected $fillable = [
        'code',
        'nom',
        'prix_vente',
        'quantite_stock',
        'categorie',
        'prix_achat',
        'cout',
        'image',
        'statut',
    ];

 
}
 