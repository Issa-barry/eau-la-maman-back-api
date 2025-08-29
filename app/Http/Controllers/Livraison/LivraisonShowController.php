<?php

namespace App\Http\Controllers\Livraison;

use App\Http\Controllers\Controller;
use App\Models\Livraison;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\JsonResponse;

class LivraisonShowController extends Controller
{
    use JsonResponseTrait;

    /**
     * Lister toutes les livraisons avec leurs relations.
     */
    public function index(): JsonResponse
    {
        try {
            $livraisons = Livraison::with([
                'commande.contact',     // livreur
                'client',
                'lignes.produit'
            ])->latest()->get();

            return $this->responseJson(true, 'Liste des livraisons récupérée avec succès.', $livraisons);

        } catch (\Throwable $e) {
            return $this->responseJson(false, 'Erreur lors de la récupération des livraisons.', [
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Afficher le détail d'une livraison par ID.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $livraison = Livraison::with([
                'commande.contact',
                'client',
                'lignes.produit'
            ])->find($id);

            if (!$livraison) {
                return $this->responseJson(false, 'Livraison introuvable.', null, 404);
            }

            return $this->responseJson(true, 'Détail de la livraison récupéré avec succès.', $livraison);

        } catch (\Throwable $e) {
            return $this->responseJson(false, 'Erreur lors de la récupération de la livraison.', [
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
 * Récupérer les livraisons liées à une commande via son numéro.
 */
public function getLivraisonByCommandeNumero(string $numero): JsonResponse
{
    try {
        $livraisons = Livraison::with([
                'commande.contact', // livreur
                'client',
                'lignes.produit'
            ])
            ->whereHas('commande', function ($query) use ($numero) {
                $query->where('numero', $numero);
            })
            ->latest()
            ->get();

        if ($livraisons->isEmpty()) {
            return $this->responseJson(false, "Aucune livraison trouvée pour la commande $numero.", null, 404);
        }

        return $this->responseJson(true, "Livraisons de la commande $numero récupérées avec succès.", $livraisons);

    } catch (\Throwable $e) {
        return $this->responseJson(false, 'Erreur lors de la récupération des livraisons de la commande.', [
            'error' => $e->getMessage()
        ], 500);
    }
}

}
