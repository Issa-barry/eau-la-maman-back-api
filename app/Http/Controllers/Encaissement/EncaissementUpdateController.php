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
                // la colonne en DB est "mode"
                'mode'              => 'nullable|string|in:espèces,orange-money,dépot-banque',
                'date_encaissement' => 'nullable|date',
                'reference'         => 'nullable|string|max:191',
                'commentaire'       => 'nullable|string',
            ]);
        } catch (ValidationException $e) {
            return $this->responseJson(false, 'Données invalides.', $e->errors(), 422);
        }

        DB::beginTransaction();

        try {
            // on a besoin de la facture liée pour recalculer les totaux
            $facture = $encaissement->facture; // belongsTo(FactureLivraison::class,'facture_id')

            // empêcher la modif si déjà soldée
            if ((float)$facture->montant_du === 0.0) {
                return $this->responseJson(false, "Impossible de modifier un encaissement : la facture est déjà soldée (montant dû = 0), statut « {$facture->statut} ».", null, 422);
            }

            // total encaissé hors encaissement courant
            $autres = (float) $facture->encaissements()
                ->where('id', '!=', $encaissement->id)
                ->sum('montant');

            $nouveauTotal = $autres + (float)$validated['montant'];

            // refuse si dépasse le total facture
            if ($nouveauTotal > (float)$facture->total) {
                return $this->responseJson(false, 'Le montant total encaissé dépasserait le total de la facture.', [
                    'total'           => (float) $facture->total,
                    'encaisse_actuel' => (float) $encaissement->montant,
                    'encaisse_total'  => (float) $autres,
                    'montant_nouveau' => (float) $validated['montant'],
                ], 422);
            }

            // mise à jour de l'encaissement
            $encaissement->update([
                'montant'           => $validated['montant'],
                'mode'              => $validated['mode'] ?? $encaissement->mode,
                'date_encaissement' => $validated['date_encaissement'] ?? $encaissement->date_encaissement,
                'reference'         => $validated['reference'] ?? $encaissement->reference,
                'commentaire'       => $validated['commentaire'] ?? $encaissement->commentaire,
            ]);

            // recalcule le statut/montant dû
            $this->updateFactureStatut($facture);

            DB::commit();

            return $this->responseJson(true, 'Encaissement mis à jour.', [
                'id'                => $encaissement->id,
                'facture_id'        => $encaissement->facture_id,
                'montant'           => (float) $encaissement->montant,
                'mode'              => $encaissement->mode,
                'reference'         => $encaissement->reference,
                'date_encaissement' => $encaissement->date_encaissement,
                'created_at'        => $encaissement->created_at,
                'updated_at'        => $encaissement->updated_at,
                'facture'           => [
                    'id'          => $facture->id,
                    'numero'      => $facture->numero,
                    'client_id'   => $facture->client_id,
                    'commande_id' => $facture->commande_id, // <-- OK
                    'total'       => (float) $facture->total,
                    'montant_du'  => (float) $facture->montant_du,
                    'statut'      => $facture->statut,
                    'created_at'  => $facture->created_at,
                    'updated_at'  => $facture->updated_at,
                ]
            ]);
        } catch (Throwable $e) {
            DB::rollBack();
            return $this->responseJson(false, 'Erreur serveur.', [
                'error' => app()->isLocal() || config('app.debug') ? $e->getMessage() : 'Erreur interne'
            ], 500);
        }
    }

    private function updateFactureStatut(FactureLivraison $facture): void
    {
        $totalEncaisse = (float) $facture->encaissements()->sum('montant');
        $facture->montant_du = max(0, (float)$facture->total - $totalEncaisse);

        if ((float)$facture->montant_du === 0.0) {
            $facture->statut = FactureLivraison::STATUT_PAYE;
        } elseif ($totalEncaisse > 0) {
            $facture->statut = FactureLivraison::STATUT_PARTIEL;
        } else {
            $facture->statut = FactureLivraison::STATUT_IMPAYE; // <-- nouveau nom
        }

        $facture->save();
    }
}
