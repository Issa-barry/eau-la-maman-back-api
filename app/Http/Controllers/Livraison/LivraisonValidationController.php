<?php

namespace App\Http\Controllers\Livraison;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Commande;
use App\Models\Livraison;
use App\Models\Produit;
use App\Traits\JsonResponseTrait;
use Illuminate\Validation\ValidationException;

class LivraisonValidationController extends Controller
{
    use JsonResponseTrait;

    /**
     * Valider une livraison depuis une commande.
     */
    public function valider(Request $request)
    {
        try {
            // Étape 1 : validation des données d'entrée
            $validated = $request->validate([
                'commande_id' => 'required|exists:commandes,id',
                'client_id' => 'required|exists:users,id',
                'date_livraison' => 'required|date',
                'produits' => 'required|array|min:1',
                'produits.*.produit_id' => 'required|exists:produits,id',
                'produits.*.quantite' => 'required|integer|min:1',
            ]);

            DB::beginTransaction();

            // Étape 2 : chargement de la commande (et du livreur via contact)
            $commande = Commande::with('contact')->findOrFail($validated['commande_id']);

            // Étape 3 : création de la livraison (sans champ `quantite`)
            $livraison = Livraison::create([
                'commande_id' => $commande->id,
                'client_id' => $validated['client_id'],
                'date_livraison' => $validated['date_livraison'],
                'statut' => 'livré', // valeur ENUM autorisée
            ]);

            // Étape 4 : création des lignes de livraison
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

            return $this->responseJson(true, 'Livraison validée avec succès.', $livraison->load([
                'client',
                'commande.contact',
                'lignes.produit'
            ]));

        } catch (ValidationException $e) {
            return $this->responseJson(false, 'Erreur de validation.', [
                'errors' => $e->errors()
            ], 422);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->responseJson(false, 'Erreur serveur lors de la validation de la livraison.', [
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
