<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LivraisonLigne extends Model
{
    use HasFactory;

    protected $fillable = [
        'livraison_id',
        'commande_ligne_id',
        'produit_id',
        'quantite',
        'montant_payer'
    ];

    public function livraison()
    {
        return $this->belongsTo(Livraison::class);
    }

   public function commandeLigne() {
  return $this->belongsTo(CommandeLigne::class, 'commande_ligne_id');
}

    public function produit()
    {
        return $this->belongsTo(Produit::class);
    }
}
