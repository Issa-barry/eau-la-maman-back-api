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
use Illuminate\Validation\Rule;
use Exception;

class CreateProduitController extends Controller
{
    use JsonResponseTrait;

    public function store(Request $request)
    {
        try {
            //  Validation de base (prix en GNF = entiers)
            $baseRules = [
                'nom'            => ['required', 'string', 'max:255'],
                'type'           => ['required', Rule::in(['vente', 'achat', 'all'])],
                'categorie'      => ['nullable', 'string', 'max:100'],

                'prix_vente'     => ['nullable', 'integer', 'min:0'],
                'prix_usine'     => ['nullable', 'integer', 'min:0'],
                'prix_achat'     => ['nullable', 'integer', 'min:0'],
                'cout'           => ['nullable', 'integer', 'min:0'],

                'quantite_stock' => ['nullable', 'integer', 'min:0'],
                'image'          => ['nullable', 'string', 'max:255'],
            ];

            $validated = $request->validate($baseRules);
            $type = strtolower($validated['type']);

            //  Règles conditionnelles spécifiques
            $errors = [];

            if ($type === 'vente') {
                if (empty($validated['prix_vente']) || $validated['prix_vente'] <= 0) {
                    $errors['prix_vente'][] = 'Le prix de vente est obligatoire et doit être supérieur à 0 pour les produits de type "vente".';
                }
                if (empty($validated['prix_usine']) || $validated['prix_usine'] <= 0) {
                    $errors['prix_usine'][] = 'Le prix usine est obligatoire et doit être supérieur à 0 pour les produits de type "vente".';
                }
            }

            if ($type === 'achat') {
                if (empty($validated['prix_achat']) || $validated['prix_achat'] <= 0) {
                    $errors['prix_achat'][] = 'Le prix d\'achat est obligatoire et doit être supérieur à 0 pour les produits de type "achat".';
                }
            }

            // (optionnel) Si tu veux aussi valider "all"
            // if ($type === 'all') {
            //     if (empty($validated['prix_vente']) || empty($validated['prix_usine']) || empty($validated['prix_achat'])) {
            //         $errors['prix'][] = 'Tous les prix doivent être renseignés pour les produits de type "all".';
            //     }
            // }

            if (!empty($errors)) {
                throw ValidationException::withMessages($errors);
            }

            //  Valeurs par défaut et normalisation
            $validated['quantite_stock'] = $validated['quantite_stock'] ?? 0;
            $validated['nom']       = strtolower(trim($validated['nom']));
            $validated['type']      = $type;
            $validated['categorie'] = isset($validated['categorie']) && trim($validated['categorie']) !== ''
                ? strtolower(trim($validated['categorie']))
                : null;

            // Génération d’un code unique
            $validated['code'] = strtolower(Str::uuid());

            // Statut automatique
            $validated['statut'] = $validated['quantite_stock'] > 0 ? 'disponible' : 'rupture';

            //  Création du produit
            $produit = Produit::create($validated);

            return $this->responseJson(true, 'Produit créé avec succès.', $produit, 201);

        } catch (ValidationException $e) {
            return $this->responseJson(false, 'Erreur de validation.', $e->errors(), 422);
        } catch (QueryException $e) {
            Log::error('Erreur SQL lors de la création du produit : '.$e->getMessage());
            return $this->responseJson(false, 'Erreur base de données.', null, 500);
        } catch (Exception $e) {
            Log::error('Erreur inattendue : '.$e->getMessage());
            return $this->responseJson(false, 'Erreur inattendue.', $e->getMessage(), 500);
        }
    }
}
