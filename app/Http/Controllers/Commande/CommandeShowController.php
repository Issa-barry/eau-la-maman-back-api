<?php

namespace App\Http\Controllers\Commande;

use App\Http\Controllers\Controller;
use App\Models\Commande;
use App\Traits\JsonResponseTrait;

class CommandeShowController extends Controller
{
    use JsonResponseTrait;

    /**
     * Affiche toutes les commandes avec leurs lignes et livraisons
     */
    public function all()
    {
        $commandes = Commande::with([
            'contact',
            'lignes.produit',
            'livraisons.lignes'
        ])->get();

        return $this->responseJson(true, "Liste des commandes", $commandes);
    }

    /**
     * Affiche une commande par son numéro avec lignes et livraisons
     */
    public function showByNumero(string $numero)
    {
        $commande = Commande::with([
            'contact',
            'lignes.produit',
            'livraisons.lignes'
        ])->where('numero', $numero)->first();

        if (!$commande) {
            return $this->responseJson(false, "Commande non trouvée", null, 404);
        }

        return $this->responseJson(true, "Commande trouvée", $commande);
    }
}
