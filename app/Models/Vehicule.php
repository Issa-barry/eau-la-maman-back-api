<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Vehicule extends Model
{
    use HasFactory;

    protected $table = 'vehicules';

    protected $fillable = [
        'type', 'marque', 'immatriculation',
        'owner_contact_id', 'livreur_contact_id',
    ];

    public function owner()
    {
        return $this->belongsTo(Contact::class, 'owner_contact_id');
    }

    public function livreur()
    {
        return $this->belongsTo(Contact::class, 'livreur_contact_id');
    }
}
