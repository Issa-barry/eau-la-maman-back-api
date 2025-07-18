<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class FactureLivraison extends Model
{
    use HasFactory;
    const STATUT_BROUILLON   = 'brouillon';
    const STATUT_PARTIEL     = 'partiel';
    const STATUT_PAYE        = 'payé';
    const STATUT_NON_PAYEE   = 'non_payée';


    protected $table = 'facture_livraisons';

    protected $fillable = [
        'client_id',
        'livraison_id',
        'montant_du',
        'numero',
        'total',
        'statut',
    ];

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function livraison()
    {
        return $this->belongsTo(Livraison::class);
    }

    public function encaissements()
    {
        return $this->hasMany(Encaissement::class, 'facture_id');
    }
}
