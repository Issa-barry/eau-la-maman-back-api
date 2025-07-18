<?php

namespace App\Http\Controllers\Factures;

use App\Http\Controllers\Controller;
use App\Models\FactureLivraison;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\JsonResponse;
use Throwable;

class FactureLivraisonIndexController extends Controller
{
    use JsonResponseTrait;

    /**
     * Lister toutes les factures de livraison.
     */
    public function index(): JsonResponse
    {
        try {
            $factures = FactureLivraison::with(['client', 'livraison'])->get();
            return $this->responseJson(true, 'Liste des factures récupérée avec succès.', $factures);
        } catch (Throwable $e) {
            return $this->responseJson(false, 'Erreur lors de la récupération des factures.', [
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Afficher le détail d'une facture.
     */
    public function show($id): JsonResponse
    {
        try {
            $facture = FactureLivraison::with(['client', 'livraison'])->find($id);

            if (!$facture) {
                return $this->responseJson(false, 'Facture non trouvée.', null, 404);
            }

            return $this->responseJson(true, 'Détail de la facture récupéré avec succès.', $facture);
        } catch (Throwable $e) {
            return $this->responseJson(false, 'Erreur lors de la récupération de la facture.', [
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
