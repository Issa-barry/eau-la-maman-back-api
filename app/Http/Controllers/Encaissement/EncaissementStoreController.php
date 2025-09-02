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
            // On charge facture + encaissements (calculs) + commande (pour la clôture)
            $facture = FactureLivraison::with(['encaissements', 'commande'])
                ->findOrFail($validated['facture_id']);

            // pas d’encaissement sur un brouillon
            if ($facture->statut === FactureLivraison::STATUT_BROUILLON) {
                DB::rollBack();
                return $this->responseJson(false, "Cette facture est en brouillon. Veuillez la valider avant d'encaisser.", null, 422);
            }

            // déjà soldée
            if ((float) $facture->montant_du === 0.0) {
                DB::rollBack();
                return $this->responseJson(
                    false,
                    "Impossible d'encaisser : la facture est déjà soldée (montant dû = 0), statut « {$facture->statut} ».",
                    null,
                    422
                );
            }

            // contrôle dépassement
            if ($validated['montant'] > (float) $facture->montant_du) {
                DB::rollBack();
                return $this->responseJson(false, 'Le montant encaissé dépasse le montant dû restant.', [
                    'montant_du'     => (float) $facture->montant_du,
                    'statut_facture' => $facture->statut,
                ], 422);
            }

            // compat ancien champ "mode"
            $mode = $validated['mode_paiement'] ?? $request->input('mode', 'espèces');
            $date = $validated['date_encaissement'] ?? now();

            // Création de l'encaissement
            $encaissement = Encaissement::create([
                'facture_id'        => $facture->id,
                'montant'           => $validated['montant'],
                'mode_paiement'     => $mode,
                'reference'         => $validated['reference'] ?? null,
                'date_encaissement' => $date,
                'commentaire'       => $validated['commentaire'] ?? null,
            ]);

            // Recalcule le statut de la facture et clôture la commande si soldée
            $this->updateFactureStatutEtCommande($facture);

            DB::commit();

            // recharge pour renvoyer les valeurs à jour
            $facture->refresh()->load(['encaissements', 'commande']);

            return $this->responseJson(true, 'Encaissement enregistré.', [
                'id'                => $encaissement->id,
                'facture_id'        => $encaissement->facture_id,
                'montant'           => (float) $encaissement->montant,
                'mode_paiement'     => $encaissement->mode_paiement,
                'reference'         => $encaissement->reference,
                'date_encaissement' => $encaissement->date_encaissement,
                'created_at'        => $encaissement->created_at,
                'updated_at'        => $encaissement->updated_at,
                'facture'           => [
                    'id'             => $facture->id,
                    'numero'         => $facture->numero,
                    'client_id'      => $facture->client_id ?? null,
                    'commande_id'    => $facture->commande_id,
                    'total'          => (float) $facture->total,
                    'montant_du'     => (float) $facture->montant_du,
                    'statut'         => $facture->statut,
                    'created_at'     => $facture->created_at,
                    'updated_at'     => $facture->updated_at,
                ],
                'commande_statut'   => optional($facture->commande)->statut,
            ]);
        } catch (Throwable $e) {
            DB::rollBack();
            return $this->responseJson(false, "Erreur serveur lors de l'encaissement.", [
                'error' => app()->isLocal() || config('app.debug') ? $e->getMessage() : 'Erreur interne'
            ], 500);
        }
    }

    /**
     * Recalcule montant_du + statut de la facture,
     * puis si facture soldée => passe la commande à "cloturé".
     */
    private function updateFactureStatutEtCommande(FactureLivraison $facture): void
    {
        // Total encaissé recalculé depuis la base (la création vient d’avoir lieu)
        $totalEncaisse = (float) $facture->encaissements()->sum('montant');

        // Si "total" est TTC dans ton modèle, garde-le. Sinon, utilise le total TTC des lignes.
        $base = (float) $facture->total;

        $facture->montant_du = max(0.0, $base - $totalEncaisse);

        if ($facture->montant_du === 0.0) {
            $facture->statut = FactureLivraison::STATUT_PAYE;
        } elseif ($totalEncaisse > 0.0) {
            $facture->statut = FactureLivraison::STATUT_PARTIEL;
        } else {
            $facture->statut = FactureLivraison::STATUT_IMPAYE;
        }
        $facture->save();

        //  Si facture soldée ⇒ commande "cloturé" (et on ne dé-clôture jamais)
        if ($facture->statut === FactureLivraison::STATUT_PAYE && $facture->commande) {
            if ($facture->commande->statut !== 'cloturé') {
                $facture->commande->update(['statut' => 'cloturé']);
            }
        }
    }
}
