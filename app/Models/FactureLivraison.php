<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FactureLivraison extends Model
{
    use HasFactory;

    public const STATUT_BROUILLON = 'brouillon';
    public const STATUT_PARTIEL   = 'partiel';
    public const STATUT_PAYE      = 'payé';
    public const STATUT_IMPAYE    = 'impayé';

    protected $table = 'facture_livraisons';

    protected $fillable = [
        'commande_id',
        'montant_du',
        'numero',
        'total',
        'statut',
    ];

    // Charge utile par défaut (ajuste si besoin)
    protected $with = ['commande.contact', 'lignes.produit', 'encaissements'];

    protected $casts = [
        'total'      => 'float',
        'montant_du' => 'float',
    ];

    protected $appends = ['montant_encaisse_total', 'total_ttc', 'reste_a_payer'];

    /** ===================== Relations ===================== */

    public function commande()
    {
        // ⚠️ Fix : classe avec majuscule
        return $this->belongsTo(Commande::class, 'commande_id');
    }

    public function encaissements()
    {
        return $this->hasMany(Encaissement::class, 'facture_id');
    }

    public function lignes()
    {
        return $this->hasMany(FactureLigne::class, 'facture_id');
    }

    /** ===================== Accessors ===================== */

    public function getMontantEncaisseTotalAttribute(): float
    {
        return (float) $this->encaissements->sum('montant');
    }

    public function getTotalTtcAttribute(): float
    {
        return (float) $this->lignes->sum('montant_ttc');
    }

    public function getResteAPayerAttribute(): float
    {
        // Si "total" est TTC, on peut directement s’appuyer dessus
        $ttc = $this->total_ttc ?: (float) $this->total;
        return (float) max(0, $ttc - $this->montant_encaisse_total);
    }

    /** ===================== Métier pratique ===================== */

    /**
     * Recalcule montant_du + statut depuis les encaissements,
     * puis, si payé, passe la commande à "cloturé".
     * À appeler après création/suppression d’un encaissement.
     */
    public function recalcAndSyncStatus(): void
    {
        $totalEncaisse = (float) $this->encaissements()->sum('montant');
        // Si ton "total" est déjà TTC, garde total; sinon utilise total_ttc recalculé
        $base = $this->total_ttc ?: (float) $this->total;

        $this->montant_du = max(0.0, $base - $totalEncaisse);

        if ($this->montant_du === 0.0) {
            $this->statut = self::STATUT_PAYE;
        } elseif ($totalEncaisse > 0) {
            $this->statut = self::STATUT_PARTIEL;
        } else {
            $this->statut = self::STATUT_IMPAYE;
        }

        $this->save();

        // Si la facture est soldée => commande "cloturé"
        if ($this->statut === self::STATUT_PAYE && $this->commande) {
            if ($this->commande->statut !== 'cloturé') {
                $this->commande->update(['statut' => 'cloturé']);
            }
        }
    }
}
