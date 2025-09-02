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

    protected $casts = [
        'quantite'         => 'int',
        'prix_unitaire_ht' => 'float',
        'montant_ht'       => 'float',
        'montant_ttc'      => 'float',
    ];

    // On veut ces deux champs dans la réponse JSON
    protected $appends = ['montant_encaisse', 'reste_a_payer'];

    public function facture()
    {
        return $this->belongsTo(FactureLivraison::class, 'facture_id');
    }

    public function produit()
    {
        return $this->belongsTo(Produit::class, 'produit_id');
    }

    /** Montant encaissé pour cette ligne (répartition au prorata du TTC) */
    public function getMontantEncaisseAttribute(): float
    {
        $facture = $this->relationLoaded('facture')
            ? $this->facture
            : $this->facture()->with(['lignes', 'encaissements'])->first();

        if (!$facture) {
            return 0.0;
        }

        $totalTtcFacture = (float) $facture->lignes->sum('montant_ttc');
        if ($totalTtcFacture <= 0) {
            return 0.0;
        }

        $encaisseTotal = (float) $facture->encaissements->sum('montant'); // adapte le nom de colonne si besoin
        $ratio         = ((float) $this->montant_ttc) / $totalTtcFacture;
        $reparti       = round($encaisseTotal * $ratio, 2);

        // on ne dépasse jamais le TTC de la ligne
        return (float) min($reparti, (float) $this->montant_ttc);
    }

    /** Reste à payer pour la ligne */
    public function getResteAPayerAttribute(): float
    {
        return (float) max(0, ((float) $this->montant_ttc) - $this->montant_encaisse);
    }
}
