<?php

namespace App\Http\Controllers\Factures;

use App\Http\Controllers\Controller;
use App\Models\FactureLivraison;
use App\Models\Livraison;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class FactureLivraisonCreateController extends Controller
{
    use JsonResponseTrait;

    /**
     * Créer une facture à partir d'une livraison sélectionnée.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Étape 1 : Validation des données reçues
            $validated = $request->validate([
                'livraison_id' => 'required|exists:livraisons,id',
            ]);
        } catch (ValidationException $e) {
            return $this->responseJson(false, 'Données invalides.', $e->errors(), 422);
        }

        DB::beginTransaction();

        try {
            // Étape 2 : Charger la livraison avec ses lignes
            $livraison = Livraison::with('lignes')->findOrFail($validated['livraison_id']);

            // Étape 3 : Vérifier si déjà facturée
            $factureExistante = FactureLivraison::where('livraison_id', $livraison->id)->first();
            if ($factureExistante) {
                return $this->responseJson(false, 'Cette livraison est déjà facturée.', $factureExistante, 409);
            }

            // Étape 4 : Vérifier les lignes de livraison
            if ($livraison->lignes->isEmpty()) {
                return $this->responseJson(false, 'Aucune ligne de livraison à facturer.', null, 400);
            }

            // Étape 5 : Calculer le montant total
            $total = $livraison->lignes->sum('montant_payer');

            // Étape 6 : Générer un numéro de facture unique
            $date = now()->format('Ymd');
            $lastId = FactureLivraison::max('id') + 1;
            $numero = 'FAC-' . $date . '-' . str_pad($lastId, 4, '0', STR_PAD_LEFT);

            // Étape 7 : Créer la facture
            $facture = FactureLivraison::create([
                'numero'        => $numero,
                'client_id'     => $livraison->client_id,
                'livraison_id'  => $livraison->id,
                'total'         => $total,
                'montant_du'    => $total,
                'statut'        => FactureLivraison::STATUT_BROUILLON,
            ]);

            DB::commit();

            return $this->responseJson(true, 'Facture créée avec succès.', $facture);
        } catch (Throwable $e) {
            DB::rollBack();

            return $this->responseJson(false, 'Erreur serveur lors de la création de la facture.', [
                'exception' => app()->environment('local') || config('app.debug') ? $e->getMessage() : 'Erreur interne',
            ], 500);
        }
    }
}
