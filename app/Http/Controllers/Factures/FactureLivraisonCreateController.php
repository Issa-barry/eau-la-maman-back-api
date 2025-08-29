<?php

namespace App\Http\Controllers\Factures;

use App\Http\Controllers\Controller;
use App\Models\FactureLivraison;
use App\Models\Commande;
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
     * Créer une facture à partir d'une commande sélectionnée.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Étape 1 : Validation des données reçues
            $validated = $request->validate([
                'commande_id' => 'required|exists:commandes,id',
            ]);
        } catch (ValidationException $e) {
            return $this->responseJson(false, 'Données invalides.', $e->errors(), 422);
        }

        DB::beginTransaction();

        try {
            // Étape 2 : Charger la commande avec ses lignes
            $commande = Commande::with('lignes')->findOrFail($validated['commande_id']);

            // Étape 3 : Vérifier si déjà facturée
            $factureExistante = FactureLivraison::where('commande_id', $commande->id)->first();
            if ($factureExistante) {
                return $this->responseJson(false, 'Cette commande est déjà facturée.', $factureExistante, 409);
            }

            // Étape 4 : Vérifier les lignes de commande
            if ($commande->lignes->isEmpty()) {
                return $this->responseJson(false, 'Aucune ligne de commande à facturer.', null, 400);
            }

            // Étape 5 : Calculer le montant total
            $total = $commande->lignes->sum(function ($ligne) {
                return $ligne->quantite_commandee * $ligne->prix_vente;
            });

            // Étape 6 : Générer un numéro de facture unique
            $date = now()->format('Ymd');
            $lastId = FactureLivraison::max('id') + 1;
            $numero = 'FAC-' . $date . '-' . str_pad($lastId, 4, '0', STR_PAD_LEFT);

            // Étape 7 : Créer la facture
            $facture = FactureLivraison::create([
                'numero'      => $numero,
                'client_id'   => $commande->contact_id,
                'commande_id' => $commande->id,
                'total'       => $total,
                'montant_du'  => $total,
                'statut'      => FactureLivraison::STATUT_BROUILLON,
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
