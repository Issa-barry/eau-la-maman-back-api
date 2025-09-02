<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Livraison extends Model
{
    use HasFactory;

    protected $fillable = [
        'commande_id',
        'quantite_livree',
        'date_livraison',
        'reference',
    ];

    /**
     * Relation avec la commande liée à cette livraison.
     */
    public function commande()
    {
        return $this->belongsTo(Commande::class);
    }

    public function lignes()
    {
        return $this->hasMany(LivraisonLigne::class);
    }

    /**
     * Génère une référence unique pour la livraison.
     */
    protected static function booted()
    {
        static::creating(function ($livraison) {
            $livraison->reference = self::generateReference();
        });
    }

    /**
     * Génère une référence unique pour chaque livraison.
     */
    public static function generateReference()
    {
        $date = now()->format('Ymd');
        $count = self::whereDate('created_at', today())->count() + 1;
        return 'LIV-' . $date . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
    }
}
