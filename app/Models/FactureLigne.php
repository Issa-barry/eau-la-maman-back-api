<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FactureLigne extends Model
{
    use HasFactory;

    protected $table = 'facture_lignes';

    protected $fillable = [
        'facture_id',
        'produit_id',
        'quantite',
        'prix_unitaire_ht',
        'montant_ht',
        'montant_ttc',
    ];

    public function facture()
    {
        return $this->belongsTo(FactureLivraison::class, 'facture_id');
    }

    public function produit()
    {
        return $this->belongsTo(Produit::class, 'produit_id');
    }
}
