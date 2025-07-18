<?php

namespace App\Http\Controllers\Factures;

use App\Http\Controllers\Controller;
use App\Models\FactureLivraison;
use App\Traits\JsonResponseTrait;

class FactureLivraisonDeleteController extends Controller
{
    use JsonResponseTrait;

    public function destroy($id)
    {
        $facture = FactureLivraison::find($id);
        if (!$facture) {
            return $this->responseJson(false, 'Facture introuvable.', null, 404);
        }

        $facture->delete();
        return $this->responseJson(true, 'Facture supprimée.');
    }
}
