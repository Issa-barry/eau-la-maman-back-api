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

class EncaissementStoreController extends Controller
{
    use JsonResponseTrait;

    public function store(Request $request)
    {
        try {
            // ✅ on valide "mode_paiement" (et pas "mode")
            $validated = $request->validate([
                'facture_id'        => 'required|exists:facture_livraisons,id',
                'montant'           => 'required|numeric|min:1',
                'mode_paiement'     => 'nullable|string|in:espèces,orange-money,dépot-banque',
                'date_encaissement' => 'nullable|date',
                'reference'         => 'nullable|string|max:191',
                'commentaire'       => 'nullable|string',
            ]);
        } catch (ValidationException $e) {
            return $this->responseJson(false, 'Données invalides.', $e->errors(), 422);
        }

        DB::beginTransaction();

        try {
            $facture = FactureLivraison::with('encaissements')->findOrFail($validated['facture_id']);

            // 🔒 pas d’encaissement sur un brouillon
            if ($facture->statut === FactureLivraison::STATUT_BROUILLON) {
                return $this->responseJson(false, "Cette facture est en brouillon. Veuillez la valider avant d'encaisser.", null, 422);
            }

            if ((float) $facture->montant_du === 0.0) {
                return $this->responseJson(false,
                    "Impossible d'encaisser : la facture est déjà soldée (montant dû = 0), statut « {$facture->statut} ».",
                    null, 422
                );
            }

            if ($validated['montant'] > (float) $facture->montant_du) {
                return $this->responseJson(false, 'Le montant encaissé dépasse le montant dû restant.', [
                    'montant_du'     => (float) $facture->montant_du,
                    'statut_facture' => $facture->statut,
                ], 422);
            }

            // ✅ lecture du mode, compatibilité avec l’ancien "mode"
            $mode = $validated['mode_paiement'] ?? $request->input('mode', 'espèces');
            $date = $validated['date_encaissement'] ?? now();

            // ✅ on enregistre en base dans la colonne "mode_paiement"
            $encaissement = Encaissement::create([
                'facture_id'        => $facture->id,
                'montant'           => $validated['montant'],
                'mode_paiement'     => $mode,
                'reference'         => $validated['reference'] ?? null,
                'date_encaissement' => $date,
                'commentaire'       => $validated['commentaire'] ?? null,
            ]);

            $this->updateFactureStatut($facture);

            DB::commit();

            return $this->responseJson(true, 'Encaissement enregistré.', [
                'id'                => $encaissement->id,
                'facture_id'        => $encaissement->facture_id,
                'montant'           => (float) $encaissement->montant,
                // ✅ réponse normalisée: "mode_paiement"
                'mode_paiement'     => $encaissement->mode_paiement,
                // (option) alias legacy si tu veux rester tolérant pendant la transition :
                // 'mode'           => $encaissement->mode_paiement,
                'reference'         => $encaissement->reference,
                'date_encaissement' => $encaissement->date_encaissement,
                'created_at'        => $encaissement->created_at,
                'updated_at'        => $encaissement->updated_at,
                'facture'           => [
                    'id'          => $facture->id,
                    'numero'      => $facture->numero,
                    'client_id'   => $facture->client_id,
                    'commande_id' => $facture->commande_id,
                    'total'       => (float) $facture->total,
                    'montant_du'  => (float) $facture->montant_du,
                    'statut'      => $facture->statut,
                    'created_at'  => $facture->created_at,
                    'updated_at'  => $facture->updated_at,
                ]
            ]);
        } catch (Throwable $e) {
            DB::rollBack();
            return $this->responseJson(false, "Erreur serveur lors de l'encaissement.", [
                'error' => app()->isLocal() || config('app.debug') ? $e->getMessage() : 'Erreur interne'
            ], 500);
        }
    }

    private function updateFactureStatut(FactureLivraison $facture): void
    {
        $totalEncaisse = (float) $facture->encaissements()->sum('montant');
        $facture->montant_du = max(0, (float) $facture->total - $totalEncaisse);

        if ((float) $facture->montant_du === 0.0) {
            $facture->statut = FactureLivraison::STATUT_PAYE;
        } elseif ($totalEncaisse > 0) {
            $facture->statut = FactureLivraison::STATUT_PARTIEL;
        } else {
            $facture->statut = FactureLivraison::STATUT_IMPAYE;
        }

        $facture->save();
    }
}
