<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Livraison extends Model
{
    use HasFactory;

    protected $fillable = [
        'commande_id',
        'client_id',
        'quantite',
        'date_livraison',
        'statut',
        'reference',
    ];

    protected $appends = ['quantite_total'];

    /**
     * Attribut calculé : somme des quantités des lignes.
     */
    public function getQuantiteTotalAttribute()
    {
        return $this->lignes->sum('quantite');
    }

    /**
     * Génération automatique de la référence à la création.
     */
    protected static function booted()
    {
        static::creating(function ($livraison) {
            $livraison->reference = self::generateReference();
        });
    }

    /**
     * Génère une référence unique du type LIV-YYYYMMDD-XXXX
     */
    public static function generateReference()
    {
        $date = now()->format('Ymd');
        $count = self::whereDate('created_at', today())->count() + 1;
        return 'LV-' . $date . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
    }

    /**
     * La commande liée à cette livraison.
     */
    public function commande()
    {
        return $this->belongsTo(Commande::class);
    }

    /**
     * Le client qui a reçu la livraison.
     */
    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /**
     * Le livreur (via contact_id de la commande).
     */
    public function livreur()
    {
        return $this->commande?->contact;
    }

    /**
     * Les lignes de produits livrés.
     */
    public function lignes()
    {
        return $this->hasMany(LivraisonLigne::class);
    }
}
