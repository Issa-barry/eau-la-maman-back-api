<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Commande extends Model
{
    use HasFactory;

    protected $fillable = [
        'numero',
        'contact_id',
        'montant_total',
        'statut',
        'reduction',
    ];

    protected $appends = [
        'qte_total',
        'qte_restante',
        'qte_livree',
        'pourcentage_livre',
        'is_entierement_livree',
    ];

    protected $casts = [
        'montant_total' => 'decimal:2',
        'reduction'     => 'decimal:2',
    ];

    /**
     * Relations
     */
    public function contact(): BelongsTo
    {
        // Si ton contact est un Contact, mets Contact::class
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

    public function livraisonLignes(): HasManyThrough
    {
        return $this->hasManyThrough(
            LivraisonLigne::class, // modèle final
            Livraison::class,      // modèle intermédiaire
            'commande_id',         // FK de livraisons vers commandes
            'livraison_id',        // FK de livraison_lignes vers livraisons
            'id',                  // PK locale (commandes)
            'id'                   // PK locale (livraisons)
        );
    }

    /**
     * Accesseurs calculés (requêtes, pas collections)
     */
    public function getQteTotalAttribute(): int
    {
        // Somme des quantités commandées
        return (int) $this->lignes()->sum('quantite_commandee');
    }

    public function getQteLivreeAttribute(): int
    {
        // Somme réelle livrée (toutes les livraisons de la commande)
        return (int) $this->livraisonLignes()->sum('quantite');
    }

    public function getQteRestanteAttribute(): int
    {
        // On ne se base plus sur quantite_restante des lignes
        return max(0, $this->qte_total - $this->qte_livree);
    }

    public function getPourcentageLivreAttribute(): float
    {
        return $this->qte_total > 0
            ? round(100 * $this->qte_livree / $this->qte_total, 2)
            : 0.0;
    }

    public function getIsEntierementLivreeAttribute(): bool
    {
        return $this->qte_total > 0 && $this->qte_livree >= $this->qte_total;
    }

    public function getStatutLabelAttribute(): string
    {
        $labels = [
            'brouillon'           => 'Brouillon',
            'annulé'              => 'Annulée',
            'livraison_en_cours'  => 'En cours de livraison',
            'livré'               => 'Livrée',
            'cloturé'             => 'Clôturée',
        ];

        return $labels[$this->statut] ?? $this->statut;
    }

    public function getStatutColorAttribute(): string
    {
        $colors = [
            'brouillon'           => 'gray',
            'annulé'              => 'red',
            'livraison_en_cours'  => 'blue',
            'livré'               => 'green',
            'cloturé'             => 'purple',
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
        // Basé sur le nouveau calcul (qte_total - qte_livree)
        return $query->where(function ($q) {
            $q->whereHas('lignes') // s'assurer qu'il y a des lignes
              ->whereRaw('(SELECT COALESCE(SUM(quantite_commandee),0) FROM commande_lignes WHERE commande_lignes.commande_id = commandes.id)
                           >
                           (SELECT COALESCE(SUM(livraison_lignes.quantite),0)
                              FROM livraisons
                              LEFT JOIN livraison_lignes ON livraison_lignes.livraison_id = livraisons.id
                             WHERE livraisons.commande_id = commandes.id)');
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
        return in_array($this->statut, ['brouillon'], true);
    }

    public function peutEtreAnnulee(): bool
    {
        return in_array($this->statut, ['brouillon', 'livraison_en_cours'], true);
    }

    public function peutEtreLivree(): bool
    {
        return in_array($this->statut, ['brouillon', 'livraison_en_cours'], true);
    }

    public function peutEtreCloturee(): bool
    {
        return $this->statut === 'livré';
    }

    public function annuler(): bool
    {
        return $this->peutEtreAnnulee()
            ? $this->update(['statut' => 'annulé'])
            : false;
    }

    public function marquerCommeEnCoursLivraison(): bool
    {
        return $this->statut === 'brouillon'
            ? $this->update(['statut' => 'livraison_en_cours'])
            : false;
    }

    public function marquerCommeLivree(): bool
    {
        // Ici on autorise le passage à "livré" même si partiellement,
        // si ta règle métier le veut (sinon garde le check is_entierement_livree)
        return $this->update(['statut' => 'livré']);
        // Ancien comportement :
        // return $this->is_entierement_livree ? $this->update(['statut' => 'livré']) : false;
    }

    public function cloturer(): bool
    {
        return $this->peutEtreCloturee()
            ? $this->update(['statut' => 'cloturé'])
            : false;
    }

    /**
     * Méthodes de calcul (montants)
     */
    public function getMontantBrut(): float
    {
        return (float) $this->lignes()->selectRaw('COALESCE(SUM(quantite_commandee * prix_vente),0) as total')->value('total');
    }

    public function getMontantRemise(): float
    {
        return (float) $this->reduction;
    }

    public function getMontantNet(): float
    {
        return max(0.0, $this->getMontantBrut() - $this->getMontantRemise());
    }

    public function getNombreProduitsDifferents(): int
    {
        return (int) $this->lignes()->count();
    }

    public function getProduitsManquants()
    {
        // produit “manquant” = encore du restant
        // On calcule via SQL : quantite_commandee - livrée > 0
        $ligneIds = $this->lignes()->pluck('id');

        $idsAvecRestant = CommandeLigne::query()
            ->whereIn('id', $ligneIds)
            ->selectRaw('commande_lignes.*, (quantite_commandee - COALESCE((
                SELECT SUM(livraison_lignes.quantite)
                FROM livraison_lignes
                WHERE livraison_lignes.commande_ligne_id = commande_lignes.id
            ),0)) as restant_calc')
            ->having('restant_calc', '>', 0)
            ->get();

        return $idsAvecRestant;
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
