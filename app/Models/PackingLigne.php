<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PackingLigne extends Model
{
    use HasFactory;

    protected $fillable = [
        'packing_id',
        'produit_id',
        'quantite_utilisee',
    ];

    public function produit()
    {
        return $this->belongsTo(Produit::class);
    }

    public function packing()
    {
        return $this->belongsTo(Packing::class);
    }
}
