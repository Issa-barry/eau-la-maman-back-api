<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Contact extends Model
{
    use HasFactory;

    public const TYPE_CLIENT_SPECIFIQUE = 'client_specifique';
    public const TYPE_LIVREUR           = 'livreur';
    public const TYPE_PROPRIETAIRE      = 'proprietaire';
    public const TYPE_PACKING           = 'packing';

    protected $fillable = [
        'nom', 'prenom', 'phone', 'ville', 'quartier', 'type',
        // 'reference' // générée automatiquement
    ];

    protected static function booted(): void
    {
        static::creating(function (self $contact) {
            if (empty($contact->reference)) {
                $contact->reference = self::generateUniqueReference($contact);
            }
        });
    }

    /**
     * Génère une référence unique au format LLNNNN (ex: AB1234).
     * LL = 2 lettres majuscules (initiales si disponibles, sinon aléatoires)
     * NNNN = 4 chiffres (0000..9999)
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

    // Normalisation simple du téléphone (utile si unique)
    public function setPhoneAttribute($value): void
    {
        $this->attributes['phone'] = preg_replace('/\s+/', '', trim((string) $value));
    }

    // Helper
    public function getNomCompletAttribute(): string
    {
        return trim(($this->prenom ?? '').' '.($this->nom ?? ''));
    }
}
