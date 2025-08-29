<?php

namespace App\Http\Controllers\Factures;

use App\Http\Controllers\Controller;
use App\Models\FactureLivraison;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FactureLivraisonUpdateController extends Controller
{
    use JsonResponseTrait;

    public function update(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'statut'     => 'nullable|in:brouillon,partiel,payé,impayé',
                'montant_du' => 'nullable|numeric|min:0',
            ]);
        } catch (ValidationException $e) {
            return $this->responseJson(false, 'Erreur de validation.', $e->errors(), 422);
        }

        $facture = FactureLivraison::find($id);
        if (!$facture) {
            return $this->responseJson(false, 'Facture non trouvée.', null, 404);
        }

        $facture->update($validated);

        return $this->responseJson(true, 'Facture mise à jour.', $facture);
    }
}
