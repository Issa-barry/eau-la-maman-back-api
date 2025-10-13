<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Vehicule extends Model
{
    use HasFactory;

    protected $table = 'vehicules';

    protected $fillable = [
        'type',
        'immatriculation',
        'nom_proprietaire',
        'prenom_proprietaire',
        'phone_proprietaire',
    ];

    // Un véhicule peut être lié à un seul contact de type 'livreur'
    public function livreur()
    {
        return $this->hasOne(Contact::class, 'vehicule_id')->where('type', Contact::TYPE_LIVREUR);
    }
}
