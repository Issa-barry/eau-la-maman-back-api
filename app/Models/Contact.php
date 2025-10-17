<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Contact extends Model
{
    use HasFactory;

    // Types alignés avec la migration
    public const TYPE_CLIENT_SPECIFIQUE = 'client_specifique';
    public const TYPE_PACKING           = 'packing';

    protected $table = 'contacts';

    protected $fillable = [
        'nom', 'prenom', 'phone', 'ville', 'quartier', 'type',
        // 'reference' générée automatiquement
    ];

    // Pour exposer nom_complet dans les réponses JSON si tu veux
    protected $appends = ['nom_complet']; 

    /* ---------------------------------
     | Hooks : référence auto (LLNNNN)
     * --------------------------------*/
    protected static function booted(): void
    {
        static::creating(function (self $contact) {
            if (empty($contact->reference)) {
                $contact->reference = self::generateUniqueReference($contact);
            }
        });
    }

    /**
     * Génère une référence unique LLNNNN (ex: AB1234).
     */
    protected static function generateUniqueReference(?self $contact = null): string
    {
        $l1 = $contact && $contact->prenom ? Str::upper(Str::substr($contact->prenom, 0, 1)) : null;
        $l2 = $contact && $contact->nom    ? Str::upper(Str::substr($contact->nom, 0, 1))    : null;

        if (!$l1) $l1 = chr(mt_rand(65, 90)); // A-Z
        if (!$l2) $l2 = chr(mt_rand(65, 90)); // A-Z

        $letters = $l1.$l2;

        do {
            $digits    = str_pad((string) mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);
            $candidate = $letters.$digits; // AB1234
        } while (self::where('reference', $candidate)->exists());

        return $candidate;
    }

    /* ---------------------------------
     | Mutators / Accessors
     * --------------------------------*/
    // Nettoyage léger du téléphone (conserve les + et chiffres si tu veux étendre)
    public function setPhoneAttribute($value): void
    {
        $this->attributes['phone'] = preg_replace('/\s+/', '', trim((string) $value));
    }

    public function getNomCompletAttribute(): string
    {
        return trim(($this->prenom ?? '').' '.($this->nom ?? ''));
    }

    /* ---------------------------------
     | Scopes pratiques
     * --------------------------------*/
    public function scopeOfType($q, ?string $type)
    {
        return $type ? $q->where('type', $type) : $q;
    }

    public function scopeSearch($q, ?string $term)
    {
        if (!$term) return $q;
        $s = trim($term);

        return $q->where(function ($qq) use ($s) {
            $qq->where('reference', 'like', "%{$s}%")
               ->orWhere('nom', 'like', "%{$s}%")
               ->orWhere('prenom', 'like', "%{$s}%")
               ->orWhere('phone', 'like', "%{$s}%")
               ->orWhere('ville', 'like', "%{$s}%")
               ->orWhere('quartier', 'like', "%{$s}%");
        });
    }
}
