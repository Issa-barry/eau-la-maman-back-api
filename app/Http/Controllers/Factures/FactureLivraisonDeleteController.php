<?php

namespace App\Http\Controllers\Factures;

use App\Http\Controllers\Controller;
use App\Models\FactureLivraison;
use App\Traits\JsonResponseTrait;
use Illuminate\Support\Facades\DB;
use Throwable;

class FactureLivraisonDeleteController extends Controller
{
    use JsonResponseTrait;

    public function destroy($id)
    {
        $facture = FactureLivraison::with('encaissements')->find($id);

        if (!$facture) {
            return $this->responseJson(false, 'Facture introuvable.', null, 404);
        }

        // Vérifier s’il y a des encaissements liés
        if ($facture->encaissements->count() > 0) {
            return $this->responseJson(false, 'Impossible de supprimer une facture avec des encaissements liés.', null, 409);
        }

        try {
            DB::beginTransaction();

            $facture->delete();

            DB::commit();

            return $this->responseJson(true, 'Facture supprimée.');
        } catch (Throwable $e) {
            DB::rollBack();

            return $this->responseJson(false, 'Erreur lors de la suppression de la facture.', [
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
