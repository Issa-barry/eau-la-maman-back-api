<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Encaissement extends Model
{
    public const MODE_ESPECES = 'espèces';
    public const MODE_OM = 'orange-money';
    public const MODE_DEPOT = 'dépot-banque';

    protected $attributes = [
        'mode_paiement' => self::MODE_ESPECES,
    ];
    
    protected $fillable = [
        'facture_id',
        'montant',
        'mode_paiement',
        'date_encaissement',
    ];

    public function facture()
    {
        return $this->belongsTo(FactureLivraison::class, 'facture_id');
    }
}
