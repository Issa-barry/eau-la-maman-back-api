<?php

namespace App\Http\Controllers\Factures;

use App\Http\Controllers\Controller;
use App\Models\FactureLivraison;
use App\Traits\JsonResponseTrait;
use Illuminate\Support\Facades\DB;

class FactureLivraisonValidateController extends Controller
{
    use JsonResponseTrait;

    /**
     * Valide une facture à l'état brouillon.
     * Route suggérée : POST /api/factures/{id}/valider
     */
    public function validateDraft($id)
    {
        // Charge la facture avec ses relations utiles
        $facture = FactureLivraison::with(['lignes.produit', 'encaissements', 'commande'])
            ->find($id);

        if (!$facture) {
            return $this->responseJson(false, 'Facture introuvable.', null, 404);
        }

        // Empêche la double-validation
        if ($facture->statut !== FactureLivraison::STATUT_BROUILLON) {
            return $this->responseJson(false, "Seules les factures en brouillon peuvent être validées.", [
                'statut_actuel' => $facture->statut
            ], 409);
        }

        // Doit contenir au moins une ligne
        if ($facture->lignes->isEmpty()) {
            return $this->responseJson(false, "Impossible de valider : la facture ne contient aucune ligne.", null, 400);
        }

        try {
            DB::beginTransaction();

            // 1) Recalcule les totaux à partir des lignes
            // (si tu gères la TVA séparément, adapte ici les colonnes)
            $total = $facture->lignes->sum(function ($l) {
                // on calcule sur montant_ttc si pas de TVA, sinon sur montant_ht + tva
                return (float)($l->montant_ttc ?? $l->montant_ht);
            });

            if ($total <= 0) {
                DB::rollBack();
                return $this->responseJson(false, "Impossible de valider : total égal à 0.", null, 400);
            }

            // 2) Met à jour total et montant dû (en tenant compte des encaissements déjà saisis)
            $totalEncaisse = (float) $facture->encaissements->sum('montant');
            $montantDu     = max(0, $total - $totalEncaisse);

            $facture->total      = $total;
            $facture->montant_du = $montantDu;

            // 3) Statut : payé si tout soldé, sinon impayé
            $facture->statut = $montantDu == 0
                ? FactureLivraison::STATUT_PAYE
                : FactureLivraison::STATUT_IMPAYE;

            // (option) si tu as une colonne validated_at dans la table :
            // $facture->validated_at = now();

            $facture->save();

            DB::commit();

            return $this->responseJson(true, "Facture validée.", $facture->fresh()->load([
                'commande', 'lignes.produit', 'encaissements'
            ]));
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->responseJson(false, "Erreur lors de la validation de la facture.", [
                'error' => app()->isLocal() || config('app.debug') ? $e->getMessage() : 'Erreur interne'
            ], 500);
        }
    }
}
