<?php

namespace App\Http\Controllers\Produit;

use App\Http\Controllers\Controller;
use App\Models\Produit;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;

class CreateProduitController extends Controller
{
    use JsonResponseTrait;

    public function store(Request $request)
    {
        try {
            // Validation de base
            $baseRules = [
                'nom' => 'required|string|max:255',
                'categorie' => 'required|string|in:vente,achat,all,matériel',
                'prix_vente' => 'nullable|numeric|min:0',
                'prix_achat' => 'nullable|numeric|min:0',
                'quantite_stock' => 'nullable|integer|min:0',
                'cout' => 'nullable|numeric|min:0',
                'image' => 'nullable|string|max:255',
            ];

            $validated = $request->validate($baseRules);

            $categorie = strtolower($validated['categorie']);

            // Appliquer règles conditionnelles obligatoires > 0
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

            // Valeur par défaut pour le stock
            $validated['quantite_stock'] = $validated['quantite_stock'] ?? 0;

            // Normalisation des textes
            $validated['nom'] = strtolower($validated['nom']);
            $validated['categorie'] = strtolower($validated['categorie']);

            // Génération d’un code unique
            $validated['code'] = strtolower(Str::uuid());

            // Détermination du statut
            $validated['statut'] = $validated['quantite_stock'] > 0 ? 'disponible' : 'rupture';

            // Création en base
            $produit = Produit::create($validated);

            return $this->responseJson(true, 'Produit créé avec succès.', $produit, 201);

        } catch (ValidationException $e) {
            return $this->responseJson(false, 'Erreur de validation.', $e->errors(), 422);
        } catch (QueryException $e) {
            Log::error('Erreur SQL lors de la création du produit : ' . $e->getMessage());
            return $this->responseJson(false, 'Erreur base de données.', null, 500);
        } catch (Exception $e) {
            Log::error('Erreur inattendue : ' . $e->getMessage());
            return $this->responseJson(false, 'Erreur inattendue.', $e->getMessage(), 500);
        }
    }
}
