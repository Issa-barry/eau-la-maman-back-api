<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FactureLivraison extends Model
{
    use HasFactory;

    const STATUT_BROUILLON = 'brouillon';
    const STATUT_PARTIEL   = 'partiel';
    const STATUT_PAYE      = 'payé';
    const STATUT_IMPAYE    = 'impayé';

    protected $table = 'facture_livraisons';

    protected $fillable = [
        'commande_id',
        'montant_du',
        'numero',
        'total',
        'statut',
    ];

    // On charge tout ce qu’il faut d’un coup
    protected $with = ['commande.contact', 'lignes.produit', 'encaissements'];

    protected $casts = [
        'total'      => 'float',
        'montant_du' => 'float',
    ];

    // quelques champs dérivés pratiques
    protected $appends = ['montant_encaisse_total', 'total_ttc', 'reste_a_payer'];

    public function commande()
    {
        return $this->belongsTo(commande::class, 'commande_id');
    }

    public function encaissements()
    {
        return $this->hasMany(Encaissement::class, 'facture_id');
    }

    public function lignes()
    {
        return $this->hasMany(FactureLigne::class, 'facture_id');
    }

    public function getMontantEncaisseTotalAttribute(): float
    {
        return (float) $this->encaissements->sum('montant'); // adapte si le champ s’appelle autrement
    }

    public function getTotalTtcAttribute(): float
    {
        return (float) $this->lignes->sum('montant_ttc');
    }

    public function getResteAPayerAttribute(): float
    {
        // si 'total' est déjà TTC tu peux t’appuyer dessus à la place
        $ttc = $this->total_ttc ?: (float) $this->total;
        return (float) max(0, $ttc - $this->montant_encaisse_total);
    }
}
