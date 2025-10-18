<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use function PHPSTORM_META\type;

class Produit extends Model
{
    protected $fillable = [
        'code',
        'nom',
        'cout',
        'image',
        'statut',
        'type',// 'vente' | 'achat' | 'all'
        'categorie',// texte libre (riz, vetement, etc...)
        'prix_usine',
        'prix_vente',
        'prix_achat',
        'quantite_stock',

    ];

 
}
 