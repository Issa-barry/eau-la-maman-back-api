<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Packing extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'date',
        'heure_debut',
        'heure_fin',
        'statut',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

  

    public function lignes()
    {
        return $this->hasMany(PackingLigne::class);
    }
}
