<?php

namespace App\Http\Controllers\Produit;

use App\Http\Controllers\Controller;
use App\Models\Produit;
use App\Traits\JsonResponseTrait;
use Illuminate\Support\Facades\Log;
use Exception;

class DeleteProduitController extends Controller
{
    use JsonResponseTrait;

    /**
     * Supprime un produit par son ID.
     */
    public function deleteById($id)
    {
        try {
            $produit = Produit::find($id);

            if (!$produit) {
                return $this->responseJson(false, 'Produit introuvable.', null, 404);
            }

            $produit->delete();

            return $this->responseJson(true, 'Produit supprimé avec succès.');
        } catch (Exception $e) {
            Log::error('Erreur lors de la suppression du produit : ' . $e->getMessage());
            return $this->responseJson(false, 'Une erreur inattendue est survenue.', null, 500);
        }
    }
}
