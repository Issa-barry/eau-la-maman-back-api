<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Packing extends Model
{
    use HasFactory;

    protected $table = 'packings';

    protected $fillable = [
        'reference',
        'contact_id',       //  nouveau lien
        'date',
        'shift',            // 'jour' | 'nuit'
        'statut',           // 'brouillon' | 'en_cours' | 'validé' | 'annulé'
        'produit_id',       //  plus de lignes, on stocke le produit ici
        'quantite_packed',  //  quantité totale packée
    ];

    protected $casts = [
        'date'            => 'date',
        'quantite_packed' => 'integer',
    ];

    // Relations
    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function produit()
    {
        return $this->belongsTo(Produit::class);
    }
}
