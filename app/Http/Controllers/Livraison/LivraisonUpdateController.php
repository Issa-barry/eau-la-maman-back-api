<?php

namespace App\Http\Controllers\Livraison;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Livraison;
use App\Models\Produit;
use App\Traits\JsonResponseTrait;
use Illuminate\Validation\ValidationException;

class LivraisonUpdateController extends Controller
{
    use JsonResponseTrait;

    /**
     * Modifier une livraison existante.
     */
    public function update(Request $request, int $id)
    {
        try {
            // Validation avec messages personnalisés
            $validated = $request->validate([
                'client_id' => 'required|exists:users,id',
                'date_livraison' => 'required|date',
                'statut' => 'required|in:en_cours,livré,en_attente,annulé',
                'produits' => 'required|array|min:1',
                'produits.*.produit_id' => 'required|exists:produits,id',
                'produits.*.quantite' => 'required|integer|min:1',
            ], [
                'client_id.exists' => 'Le client sélectionné est introuvable.',
                'produits.*.produit_id.exists' => 'Un des produits sélectionnés est invalide.',
                'produits.*.quantite.min' => 'La quantité doit être supérieure à zéro.',
            ]);

            DB::beginTransaction();

            $livraison = Livraison::with('lignes')->find($id);

            if (!$livraison) {
                return $this->responseJson(false, 'Livraison introuvable.', null, 404);
            }

            // Mise à jour des champs principaux
            $livraison->update([
                'client_id' => $validated['client_id'],
                'date_livraison' => $validated['date_livraison'],
                'statut' => $validated['statut'],
            ]);

            // Réinitialiser les lignes existantes
            $livraison->lignes()->delete();

            foreach ($validated['produits'] as $item) {
                $produit = Produit::findOrFail($item['produit_id']);
                $montant = $produit->prix_vente * $item['quantite'];

                $livraison->lignes()->create([
                    'produit_id' => $produit->id,
                    'quantite' => $item['quantite'],
                    'montant_payer' => $montant,
                ]);
            }

            DB::commit();

            return $this->responseJson(true, 'Livraison mise à jour avec succès.', $livraison->load([
                'client',
                'commande.contact',
                'lignes.produit'
            ]));

        } catch (ValidationException $e) {
            return $this->responseJson(false, 'Erreur de validation.', [
                'message' => 'Certains champs sont invalides. Veuillez corriger les erreurs ci-dessous.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->responseJson(false, 'Erreur serveur lors de la mise à jour.', [
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
