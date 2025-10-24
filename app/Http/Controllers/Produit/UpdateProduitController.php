<?php

namespace App\Http\Controllers\Produit;

use App\Http\Controllers\Controller;
use App\Models\Produit;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
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

            // ✔️ Règles de base (entiers GNF)
            $rules = [
                'nom'            => ['sometimes','required','string','max:255'],
                'type'           => ['sometimes','required', Rule::in(['vente','achat','all'])],
                'categorie'      => ['nullable','string','max:100'],

                'prix_vente'     => ['nullable','integer','min:0'],
                'prix_usine'     => ['nullable','integer','min:0'],
                'prix_achat'     => ['nullable','integer','min:0'],
                'cout'           => ['nullable','integer','min:0'],

                'quantite_stock' => ['nullable','integer','min:0'],
                'image'          => ['nullable','string','max:255'],
                'statut'         => ['nullable', Rule::in(['disponible','rupture','archivé'])], // si tu veux permettre le set direct
            ];

            $validated = $request->validate($rules);

            // 🔀 Valeurs effectives (payload ⟶ sinon valeur existante)
            $effectiveType       = strtolower($validated['type'] ?? $produit->type);
            $effectivePrixVente  = $validated['prix_vente'] ?? $produit->prix_vente;
            $effectivePrixUsine  = $validated['prix_usine'] ?? $produit->prix_usine;
            $effectivePrixAchat  = $validated['prix_achat'] ?? $produit->prix_achat;

            // ✅ Règles conditionnelles métier
            $errors = [];

            if ($effectiveType === 'vente') {
                if (empty($effectivePrixVente) || $effectivePrixVente <= 0) {
                    $errors['prix_vente'][] = 'Obligatoire (> 0) quand type = "vente".';
                }
                if (empty($effectivePrixUsine) || $effectivePrixUsine <= 0) {
                    $errors['prix_usine'][] = 'Obligatoire (> 0) quand type = "vente".';
                }
            }

            if ($effectiveType === 'achat') {
                if (empty($effectivePrixAchat) || $effectivePrixAchat <= 0) {
                    $errors['prix_achat'][] = 'Obligatoire (> 0) quand type = "achat".';
                }
            }

            if (!empty($errors)) {
                throw ValidationException::withMessages($errors);
            }

            // 🧹 Normalisations
            if (isset($validated['nom'])) {
                $validated['nom'] = strtolower(trim($validated['nom']));
            }
            if (array_key_exists('categorie', $validated)) {
                $validated['categorie'] = (isset($validated['categorie']) && trim($validated['categorie']) !== '')
                    ? strtolower(trim($validated['categorie']))
                    : null;
            }
            if (isset($validated['type'])) {
                $validated['type'] = $effectiveType;
            }

            // 🔁 Statut auto si on change le stock (sauf si statut explicitement fourni)
            if (array_key_exists('quantite_stock', $validated) && !array_key_exists('statut', $validated)) {
                $validated['statut'] = ($validated['quantite_stock'] ?? $produit->quantite_stock) > 0 ? 'disponible' : 'rupture';
            }

            // 💾 Mise à jour
            $produit->update($validated);

            return $this->responseJson(true, 'Produit mis à jour avec succès.', $produit);

        } catch (ValidationException $e) {
            return $this->responseJson(false, 'Erreur de validation.', $e->errors(), 422);
        } catch (QueryException $e) {
            Log::error('Erreur SQL lors de la mise à jour du produit : '.$e->getMessage());
            return $this->responseJson(false, 'Erreur base de données.', null, 500);
        } catch (Exception $e) {
            Log::error('Erreur inattendue lors de la mise à jour du produit : '.$e->getMessage());
            return $this->responseJson(false, 'Erreur inattendue.', $e->getMessage(), 500);
        }
    }
}
