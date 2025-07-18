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

    /**
     * Enregistrer un nouvel encaissement et mettre à jour la facture associée.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'facture_id'         => 'required|exists:facture_livraisons,id',
                'montant'            => 'required|numeric|min:1',
                'mode_paiement'      => 'nullable|string|in:espèces,orange-money,dépot-banque',
                'date_encaissement'  => 'nullable|date',
            ]);
        } catch (ValidationException $e) {
            return $this->responseJson(false, 'Données invalides.', $e->errors(), 422);
        }

        DB::beginTransaction();

        try {
            $facture = FactureLivraison::with('encaissements')->findOrFail($validated['facture_id']);

            //  Refuser tout encaissement si montant dû = 0, même si statut ≠ payé
            if ($facture->montant_du == 0) {
                return $this->responseJson(false, 'Impossible d\'encaisser : la facture est déjà soldée (montant dû = 0), même si son statut est "' . $facture->statut . '".', null, 422);
            }

            //  Refuser si dépassement du montant dû
            if ($validated['montant'] > $facture->montant_du) {
                return $this->responseJson(false, 'Le montant encaissé dépasse le montant dû restant.', [
                    'montant_du' => (float) $facture->montant_du,
                    'statut_facture' => $facture->statut,
                ], 422);
            }

            //  Valeurs par défaut
            $validated['mode_paiement']     = $validated['mode_paiement'] ?? 'espèces';
            $validated['date_encaissement'] = $validated['date_encaissement'] ?? now();

            //  Création encaissement
            $encaissement = Encaissement::create([
                'facture_id'        => $facture->id,
                'montant'           => $validated['montant'],
                'mode_paiement'     => $validated['mode_paiement'],
                'date_encaissement' => $validated['date_encaissement'],
            ]);

            //  Mise à jour facture
            $this->updateFactureStatut($facture);

            DB::commit();

            return $this->responseJson(true, 'Encaissement enregistré.', [
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
            return $this->responseJson(false, 'Erreur serveur lors de l\'encaissement.', [
                'error' => app()->isLocal() || config('app.debug') ? $e->getMessage() : 'Erreur interne'
            ], 500);
        }
    }

    /**
     * Met à jour le montant dû et le statut de la facture.
     */
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
