<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Commande extends Model
{
    use HasFactory;

    protected $fillable = [
        'numero',
        'contact_id',
        'montant_total',
        'statut',
        'reduction'
    ];

    protected $appends = [
        'qte_total',
        'qte_restante', 
        'qte_livree',
        'pourcentage_livre',
        'is_entierement_livree'
    ];

    protected $casts = [
        'montant_total' => 'decimal:2',
        'reduction' => 'decimal:2',
    ];

    /**
     * Relations
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(User::class, 'contact_id');
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(CommandeLigne::class);
    }

    public function livraisons(): HasMany
    {
        return $this->hasMany(Livraison::class);
    }

    /**
     * Accesseurs calculés
     */
    public function getQteTotalAttribute(): int
    {
        return $this->lignes->sum('quantite_commandee');
    }

    public function getQteRestanteAttribute(): int
    {
        return $this->lignes->sum('quantite_restante');
    }

    public function getQteLivreeAttribute(): int
    {
        return $this->qte_total - $this->qte_restante;
    }

    public function getPourcentageLivreAttribute(): float
    {
        if ($this->qte_total <= 0) {
            return 0.0;
        }
        
        return round(($this->qte_livree / $this->qte_total) * 100, 2);
    }

    public function getIsEntierementLivreeAttribute(): bool
    {
        return $this->qte_restante <= 0;
    }

    public function getStatutLabelAttribute(): string
    {
        $labels = [
            'brouillon' => 'Brouillon',
            'annulé' => 'Annulée',
            'livraison_en_cours' => 'En cours de livraison',
            'livré' => 'Livrée',
            'cloturé' => 'Clôturée'
        ];

        return $labels[$this->statut] ?? $this->statut;
    }

    public function getStatutColorAttribute(): string
    {
        $colors = [
            'brouillon' => 'gray',
            'annulé' => 'red',
            'livraison_en_cours' => 'blue',
            'livré' => 'green',
            'cloturé' => 'purple'
        ];

        return $colors[$this->statut] ?? 'gray';
    }

    /**
     * Scopes
     */
    public function scopeEnCours($query)
    {
        return $query->whereIn('statut', ['brouillon', 'livraison_en_cours']);
    }

    public function scopeLivrees($query)
    {
        return $query->where('statut', 'livré');
    }

    public function scopeAvecQuantiteRestante($query)
    {
        return $query->whereHas('lignes', function ($q) {
            $q->where('quantite_restante', '>', 0);
        });
    }

    public function scopeParContact($query, $contactId)
    {
        return $query->where('contact_id', $contactId);
    }

    public function scopeParStatut($query, $statut)
    {
        return $query->where('statut', $statut);
    }

    public function scopeEntreDesDates($query, $dateDebut, $dateFin)
    {
        return $query->whereBetween('created_at', [$dateDebut, $dateFin]);
    }

    /**
     * Méthodes métier
     */
    public function peutEtreModifiee(): bool
    {
        return in_array($this->statut, ['brouillon']);
    }

    public function peutEtreAnnulee(): bool
    {
        return in_array($this->statut, ['brouillon', 'livraison_en_cours']);
    }

    public function peutEtreLivree(): bool
    {
        return in_array($this->statut, ['brouillon', 'livraison_en_cours']);
    }

    public function peutEtreCloturee(): bool
    {
        return $this->statut === 'livré';
    }

    public function annuler(): bool
    {
        if (!$this->peutEtreAnnulee()) {
            return false;
        }

        return $this->update(['statut' => 'annulé']);
    }

    public function marquerCommeEnCoursLivraison(): bool
    {
        if ($this->statut !== 'brouillon') {
            return false;
        }

        return $this->update(['statut' => 'livraison_en_cours']);
    }

    public function marquerCommeLivree(): bool
    {
        if (!$this->is_entierement_livree) {
            return false;
        }

        return $this->update(['statut' => 'livré']);
    }

    public function cloturer(): bool
    {
        if (!$this->peutEtreCloturee()) {
            return false;
        }

        return $this->update(['statut' => 'cloturé']);
    }

    /**
     * Méthodes de calcul
     */
    public function getMontantBrut(): float
    {
        return $this->lignes->sum(function ($ligne) {
            return $ligne->quantite_commandee * $ligne->prix_vente;
        });
    }

    public function getMontantRemise(): float
    {
        return $this->reduction;
    }

    public function getMontantNet(): float
    {
        return max(0, $this->getMontantBrut() - $this->getMontantRemise());
    }

    public function getNombreProduitsDifferents(): int
    {
        return $this->lignes->count();
    }

    public function getProduitsManquants()
    {
        return $this->lignes->filter(function ($ligne) {
            return $ligne->quantite_restante > 0;
        });
    }

    /**
     * Boot du modèle
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($commande) {
            if (empty($commande->numero)) {
                $commande->numero = static::generateNumero();
            }
        });
    }

    /**
     * Génère un numéro de commande unique
     */
    public static function generateNumero(): string
    {
        $lastCommande = static::latest('id')->first();
        $nextId = $lastCommande ? $lastCommande->id + 1 : 1;
        
        return 'CO' . str_pad($nextId, 8, '0', STR_PAD_LEFT);
    }
}