<?php

namespace App\Http\Controllers\Encaissement;

use App\Http\Controllers\Controller;
use App\Models\Encaissement;
use App\Traits\JsonResponseTrait;
use Illuminate\Support\Facades\DB;
use Throwable;

class EncaissementDeleteController extends Controller
{
    use JsonResponseTrait;

    public function destroy($id)
    {
        $encaissement = Encaissement::find($id);
        if (!$encaissement) {
            return $this->responseJson(false, 'Encaissement introuvable.', null, 404);
        }

        DB::beginTransaction();

        try {
            $facture = $encaissement->facture;
            $encaissement->delete();
            $this->updateStatutFacture($facture);

            DB::commit();
            return $this->responseJson(true, 'Encaissement supprimé.');
        } catch (Throwable $e) {
            DB::rollBack();
            return $this->responseJson(false, 'Erreur serveur.', ['error' => $e->getMessage()], 500);
        }
    }

    private function updateStatutFacture($facture)
    {
        $totalEncaisse = $facture->encaissements()->sum('montant');
        $facture->montant_du = max(0, $facture->total - $totalEncaisse);
        $facture->statut = $facture->montant_du == 0 ? 'payé'
                          : ($totalEncaisse > 0 ? 'partiel' : 'non_payée');
        $facture->save();
    }
}
