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
    use HasFactory, Notifiable, HasApiTokens, HasRoles;

    /**
     * Attributs assignables en masse.
     */
    protected $fillable = [
        'email',
        'password',
        'reference',
        'civilite',
        'prenom',
        'nom',
        'phone',
        'date_naissance',
        'adresse_id',
        'role_id',
        'agence_id',
        'statut',
     ];

    /**
     * Attributs ajoutés automatiquement au JSON.
     */
    protected $appends = ['nom_complet'];

    /**
     * Accessor: nom complet (virtuel).
     */
    public function getNomCompletAttribute(): string
    {
        return trim(($this->prenom ?? '').' '.($this->nom ?? ''));
    }

    /**
     * Mutator: normalise le téléphone (supprime espaces).
     */
    public function setPhoneAttribute($value): void
    {
        $this->attributes['phone'] = preg_replace('/\s+/', '', trim((string) $value));
    }

    /**
     * Relations.
     */
    public function role()
    {
        return $this->roles()->first();
    }

    public function adresse()
    {
        return $this->belongsTo(Adresse::class);
    }

    public function agence()
    {
        return $this->belongsTo(Agence::class);
    }

    /**
     * Raccourci pour retourner le nom du premier rôle.
     */
    public function getRoleAttribute()
    {
        return $this->roles->pluck('name')->first();
    }

    /**
     * Attributs cachés (JSON).
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Casts.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            // pas de casts pour type_client / type_vehicule
        ];
    }

    /**
     * Hooks du modèle.
     */
    protected static function booted(): void
    {
        static::creating(function (self $user) {
            if (empty($user->reference)) {
                $user->reference = self::generateUniqueReference();
            }
        });

        static::deleting(function (self $user) {
            if ($user->adresse) {
                $user->adresse->delete();
            }
        });
    }

    /**
     * Génère une référence unique.
     */
    public static function generateUniqueReference(): string
    {
        do {
            $reference = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 2))
                       . rand(10, 99)
                       . rand(0, 9);
        } while (self::where('reference', $reference)->exists());

        return $reference;
    }

    /**
     * URL de vérification d'email (valide 60 minutes).
     */
    public function verificationUrl($notifiable): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $notifiable->getKey(), 'hash' => sha1($notifiable->getEmailForVerification())]
        );
    }

    /**
     * Notification de réinitialisation de mot de passe.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new CustomResetPassword($token));
    }
}
