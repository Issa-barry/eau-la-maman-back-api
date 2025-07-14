<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Commande extends Model
{
    use HasFactory;

    protected $fillable = [
        'numero',
        'contact_id',
        'montant_total',
        'statut',
        'reduction'

    ];

     protected $appends = ['qte_total'];

    public function contact()
    {
        return $this->belongsTo(User::class, 'contact_id');
    }

    public function lignes()
    {
        return $this->hasMany(CommandeLigne::class);
    }

    public function getQteTotalAttribute()
{
    return $this->lignes->sum('quantite');
}

}
