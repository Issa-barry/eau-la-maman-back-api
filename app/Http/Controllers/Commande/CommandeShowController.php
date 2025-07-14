<?php 
namespace App\Http\Controllers\Commande;

use App\Http\Controllers\Controller;
use App\Models\Commande;
use App\Traits\JsonResponseTrait;

class CommandeShowController extends Controller
{
    use JsonResponseTrait;

    /**
     * Affiche toutes les commandes
     */
    public function all()
    {
        $commandes = Commande::with(['contact', 'lignes.produit'])->get();
        return $this->responseJson(true, "Liste des commandes", $commandes);
    }

    /**
     * Affiche une commande par son numéro
     */
    public function showByNumero(string $numero)
    {
        $commande = Commande::with(['contact', 'lignes.produit'])
            ->where('numero', $numero)
            ->first();

        if (!$commande) {
            return $this->responseJson(false, "Commande non trouvée", null, 404);
        }

        return $this->responseJson(true, "Commande trouvée", $commande);
    }
}
