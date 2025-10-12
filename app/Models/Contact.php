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
        'nom','prenom','phone','ville','quartier','type',
        // 'reference' //  on NE remplit pas depuis le client : générée automatiquement
    ];

    protected static function booted(): void
    {
        static::creating(function (self $contact) {
            if (empty($contact->reference)) {
                $contact->reference = self::generateUniqueReference();
            }
        });
    }

    protected static function generateUniqueReference(): string
    {
        // Format: CT-YYYY-XXXXXX (lettres/chiffres)
        $prefix = 'CT-'.date('Y').'-';
        do {
            $candidate = $prefix . strtoupper(Str::random(6));
        } while (self::where('reference', $candidate)->exists());

        return $candidate;
    }

    // Helpers
    public function getNomCompletAttribute(): string
    {
        return trim(($this->prenom ?? '').' '.($this->nom ?? ''));
    }
}
