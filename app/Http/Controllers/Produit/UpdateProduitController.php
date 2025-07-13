<?php

namespace App\Http\Controllers\Produit;

use App\Http\Controllers\Controller;
use App\Models\Produit;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Exception;

class UpdateProduitController extends Controller
{
    use JsonResponseTrait;

    public function update(Request $request, $id)
    {
        try {
            $produit = Produit::find($id);
            if (!$produit) {
                return $this->responseJson(false, 'Produit introuvable.', null, 404);
            }

            // Règles de base
            $rules = [
                'nom' => 'sometimes|required|string|max:255',
                'categorie' => 'sometimes|required|string|in:vente,achat,all,matériel',
                'prix_vente' => 'nullable|numeric|min:0',
                'prix_achat' => 'nullable|numeric|min:0',
                'quantite_stock' => 'nullable|integer|min:0',
                'cout' => 'nullable|numeric|min:0',
                'image' => 'nullable|string|max:255',
            ];

            $validated = $request->validate($rules);

            // Catégorie à prendre en compte (valeur modifiée ou existante)
            $categorie = strtolower($validated['categorie'] ?? $produit->categorie);

            // Règles conditionnelles obligatoires selon catégorie
            if (in_array($categorie, ['vente', 'all', 'matériel'])) {
                if (!isset($validated['prix_vente']) || $validated['prix_vente'] <= 0) {
                    throw ValidationException::withMessages([
                        'prix_vente' => 'Le prix de vente est requis et doit être strictement supérieur à 0 pour cette catégorie.'
                    ]);
                }
            }

            if (in_array($categorie, ['achat', 'all', 'matériel'])) {
                if (!isset($validated['prix_achat']) || $validated['prix_achat'] <= 0) {
                    throw ValidationException::withMessages([
                        'prix_achat' => 'Le prix d\'achat est requis et doit être strictement supérieur à 0 pour cette catégorie.'
                    ]);
                }
            }

            // Mise à jour des champs texte
            if (isset($validated['nom'])) {
                $validated['nom'] = strtolower($validated['nom']);
            }
            if (isset($validated['categorie'])) {
                $validated['categorie'] = strtolower($validated['categorie']);
            }

            // Mise à jour du statut selon quantité
            if (array_key_exists('quantite_stock', $validated)) {
                $validated['statut'] = $validated['quantite_stock'] > 0 ? 'disponible' : 'rupture';
            }

            // Mise à jour en base
            $produit->update($validated);

            return $this->responseJson(true, 'Produit mis à jour avec succès.', $produit);

        } catch (ValidationException $e) {
            return $this->responseJson(false, 'Erreur de validation.', $e->errors(), 422);
        } catch (QueryException $e) {
            Log::error('Erreur SQL lors de la mise à jour du produit : ' . $e->getMessage());
            return $this->responseJson(false, 'Erreur base de données.', null, 500);
        } catch (Exception $e) {
            Log::error('Erreur inattendue lors de la mise à jour du produit : ' . $e->getMessage());
            return $this->responseJson(false, 'Erreur inattendue.', $e->getMessage(), 500);
        }
    }
}
