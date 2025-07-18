<?php

namespace App\Http\Controllers\Encaissement;

use App\Http\Controllers\Controller;
use App\Models\Encaissement;
use App\Models\FactureLivraison;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class EncaissementUpdateController extends Controller
{
    use JsonResponseTrait;

    public function update(Request $request, $id)
    {
        $encaissement = Encaissement::find($id);
        if (!$encaissement) {
            return $this->responseJson(false, 'Encaissement non trouvé.', null, 404);
        }

        try {
            $validated = $request->validate([
                'montant'           => 'required|numeric|min:1',
                'mode_paiement'     => 'nullable|string|in:espèces,orange-money,dépot-banque',
                'date_encaissement' => 'nullable|date',
            ]);
        } catch (ValidationException $e) {
            return $this->responseJson(false, 'Données invalides.', $e->errors(), 422);
        }

        DB::beginTransaction();

        try {
            $facture = $encaissement->facture;

            //  Refuser la modification si la facture est déjà soldée
            if ($facture->montant_du == 0) {
                return $this->responseJson(false, 'Impossible de modifier un encaissement : la facture est déjà soldée (montant dû = 0), même si son statut est "' . $facture->statut . '".', null, 422);
            }

            //  Recalculer le total encaissé sans l'encaissement actuel
            $autres = $facture->encaissements()->where('id', '!=', $encaissement->id)->sum('montant');
            $nouveauTotal = $autres + $validated['montant'];

            // Refuser si le nouveau total dépasse le total de la facture
            if ($nouveauTotal > $facture->total) {
                return $this->responseJson(false, 'Le montant total encaissé dépasserait le total de la facture.', [
                    'total'        => (float) $facture->total,
                    'encaisse_actuel' => (float) $encaissement->montant,
                    'encaisse_total'  => (float) $autres,
                    'montant_nouveau' => (float) $validated['montant']
                ], 422);
            }

            //  Mise à jour
            $encaissement->update([
                'montant'           => $validated['montant'],
                'mode_paiement'     => $validated['mode_paiement'] ?? $encaissement->mode_paiement,
                'date_encaissement' => $validated['date_encaissement'] ?? $encaissement->date_encaissement,
            ]);

            $this->updateFactureStatut($facture);

            DB::commit();

            return $this->responseJson(true, 'Encaissement mis à jour.', [
                'id'                => $encaissement->id,
                'facture_id'        => $encaissement->facture_id,
                'montant'           => $encaissement->montant,
                'mode_paiement'     => $encaissement->mode_paiement,
                'date_encaissement' => $encaissement->date_encaissement,
                'created_at'        => $encaissement->created_at,
                'updated_at'        => $encaissement->updated_at,
                'facture'           => [
                    'id'           => $facture->id,
                    'numero'       => $facture->numero,
                    'client_id'    => $facture->client_id,
                    'livraison_id' => $facture->livraison_id,
                    'total'        => (float) $facture->total,
                    'montant_du'   => (float) $facture->montant_du,
                    'statut'       => $facture->statut,
                    'created_at'   => $facture->created_at,
                    'updated_at'   => $facture->updated_at,
                ]
            ]);
        } catch (Throwable $e) {
            DB::rollBack();
            return $this->responseJson(false, 'Erreur serveur.', [
                'error' => app()->isLocal() || config('app.debug') ? $e->getMessage() : 'Erreur interne'
            ], 500);
        }
    }

    private function updateFactureStatut(FactureLivraison $facture)
    {
        $totalEncaisse = $facture->encaissements()->sum('montant');
        $facture->montant_du = max(0, $facture->total - $totalEncaisse);

        if ($facture->montant_du == 0) {
            $facture->statut = FactureLivraison::STATUT_PAYE;
        } elseif ($totalEncaisse > 0) {
            $facture->statut = FactureLivraison::STATUT_PARTIEL;
        } else {
            $facture->statut = FactureLivraison::STATUT_NON_PAYEE;
        }

        $facture->save();
    }
}
