<?php

namespace App\Http\Controllers\Produit;

use App\Http\Controllers\Controller;
use App\Models\Produit;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class ShowProduitController extends Controller
{
    use JsonResponseTrait;

    /**
     * Affiche tous les produits.
     */
    public function index()
    {
        try {
            $produits = Produit::all();
            return $this->responseJson(true, 'Liste des produits récupérée avec succès.', $produits);
        } catch (Exception $e) {
            Log::error('Erreur lors de la récupération des produits : ' . $e->getMessage());
            return $this->responseJson(false, 'Erreur inattendue lors de la récupération des produits.', null, 500);
        }
    }

    /**
     * Affiche un produit par ID.
     */
    public function getById($id)
    {
        try {
            $produit = Produit::find($id);

            if (!$produit) {
                return $this->responseJson(false, 'Produit introuvable.', null, 404);
            }

            return $this->responseJson(true, 'Produit trouvé.', $produit);
        } catch (Exception $e) {
            Log::error('Erreur lors de la récupération du produit : ' . $e->getMessage());
            return $this->responseJson(false, 'Erreur inattendue lors de la récupération du produit.', null, 500);
        }
    }
}
