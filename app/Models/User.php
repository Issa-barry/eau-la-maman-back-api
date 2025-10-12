<?php

namespace App\Models;

use App\Notifications\CustomResetPassword;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use URL;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, HasRoles;

    // ---- Types de client ----
    public const TYPE_CLIENT_SPECIFIQUE = 'specifique';
    public const TYPE_CLIENT_VEHICULE   = 'vehicule';

    // ---- Types de véhicule (si type_client = 'vehicule') ----
    public const VEHICULE_CAMION       = 'camion';
    public const VEHICULE_FOURGONETTE  = 'fourgonette';
    public const VEHICULE_TRICYCLE     = 'tricycle';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'email',
        'password',
        'reference',
        'civilite',
        'nom_complet',
        'phone',
        'date_naissance',
        'adresse_id',
        'role_id',
        'agence_id',

        //  nouveaux champs
        'type_client',                 // 'specifique' | 'vehicule'
        'type_vehicule',               // 'camion' | 'fourgonette' | 'tricycle' (nullable si specifique)
    ];

    public function role()
    {
        return $this->roles()->first(); // Retourne le premier rôle associé
    }

    public function adresse()
    {
        return $this->belongsTo(Adresse::class);
    }

    public function agence()
    {
        return $this->belongsTo(Agence::class);
    }

    // Relation to include role data
    public function getRoleAttribute()
    {
        return $this->roles->pluck('name')->first(); // Return the first role name as string
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',

            // (optionnel, ici déjà string par défaut)
            'type_client'       => 'string',
            'vehicule_type'     => 'string',
        ];
    }

    // ==================== Helpers / Scopes ====================

    public function getIsVehiculeClientAttribute(): bool
    {
        return $this->type_client === self::TYPE_CLIENT_VEHICULE;
    }

    public function scopeSpecifique($query)
    {
        return $query->where('type_client', self::TYPE_CLIENT_SPECIFIQUE);
    }

    public function scopeVehicule($query)
    {
        return $query->where('type_client', self::TYPE_CLIENT_VEHICULE);
    }

    // ==================== Boot / Hooks ====================

    protected static function booted()
    {
        static::creating(function ($user) {
            $user->reference = self::generateUniqueReference();

            // Sécurise la cohérence à la création :
            if (($user->type_client ?? self::TYPE_CLIENT_SPECIFIQUE) === self::TYPE_CLIENT_SPECIFIQUE) {
                $user->type_vehicule = null;
            }
        });

        static::updating(function ($user) {
            // Si on repasse en 'specifique', on purge les champs véhicule
            if ($user->type_client === self::TYPE_CLIENT_SPECIFIQUE) {
                $user->type_vehicule = null; 
            }
        });

        static::deleting(function ($user) {
            // Supprime l'adresse associée si elle existe
            if ($user->adresse) {
                $user->adresse->delete();
            }
        });
    }

    public static function generateUniqueReference()
    {
        do {
            $reference = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 2)) . rand(10, 99) . rand(0, 9);
        } while (self::where('reference', $reference)->exists());

        return $reference;
    }

    /**
     * Get the email verification URL for the given user.
     *
     * @return string
     */
    public function verificationUrl($notifiable)
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(1), // Lien expire après 1 minute
            ['id' => $notifiable->getKey(), 'hash' => sha1($notifiable->getEmailForVerification())]
        );
    }

    public function sendPasswordResetNotification($token): void
{
    $this->notify(new CustomResetPassword($token));
}
}
