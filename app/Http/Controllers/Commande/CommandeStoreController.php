<?php

namespace App\Http\Controllers\Commande;

use App\Http\Controllers\Controller;
use App\Models\Commande;
use App\Models\CommandeLigne;
use App\Models\Produit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Traits\JsonResponseTrait;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

class CommandeStoreController extends Controller
{
    use JsonResponseTrait;

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'contact_id' => 'required|exists:users,id',
                'reduction' => 'nullable|numeric|min:0',
                'lignes' => 'required|array|min:1',
                'lignes.*.produit_id' => 'required|exists:produits,id',
                'lignes.*.quantite' => 'required|integer|min:1',
                'lignes.*.prix_vente' => 'nullable|numeric|min:0',
            ], [
                'contact_id.required' => 'Le contact est requis.',
                'contact_id.exists' => 'Contact introuvable.',
                'lignes.required' => 'La commande doit contenir au moins un produit.',
                'lignes.*.produit_id.required' => 'Produit requis.',
                'lignes.*.produit_id.exists' => 'Produit introuvable.',
                'lignes.*.quantite.required' => 'Quantité requise.',
                'lignes.*.quantite.integer' => 'La quantité doit être un nombre entier.',
                'lignes.*.quantite.min' => 'La quantité doit être d\'au moins 1.',
                'lignes.*.prix_vente.numeric' => 'Le prix de vente doit être un nombre.',
                'lignes.*.prix_vente.min' => 'Le prix de vente doit être supérieur ou égal à 0.',
            ]);
        } catch (ValidationException $e) {
            return $this->responseJson(
                false,
                'Données invalides',
                $e->errors(),
                422
            );
        }

        DB::beginTransaction();

        try {
            $reduction = $validated['reduction'] ?? 0;

            $commande = Commande::create([
                'numero' => '',
                'contact_id' => $validated['contact_id'],
                'montant_total' => 0,
                'statut' => 'brouillon',
                'reduction' => $reduction,
            ]);

            $commande->update([
                'numero' => 'CO' . str_pad($commande->id, 8, '0', STR_PAD_LEFT)
            ]);

            $total = 0;

            foreach ($validated['lignes'] as $ligne) {
                $produit = Produit::findOrFail($ligne['produit_id']);

                $quantite = $ligne['quantite'];
                $prixVente = isset($ligne['prix_vente']) && is_numeric($ligne['prix_vente'])
                    ? floatval($ligne['prix_vente'])
                    : $produit->prix_vente;

                $sousTotal = $prixVente * $quantite;
                $total += $sousTotal;

                CommandeLigne::create([
                    'commande_id'        => $commande->id,
                    'produit_id'         => $produit->id,
                    'prix_vente'         => $prixVente,
                    'quantite_commandee' => $quantite,
                    'quantite_restante'  => $quantite, // On initialise la quantité restante
                ]);
            }

            $commande->update([
                'montant_total' => max(0, $total - $reduction)
            ]);

            DB::commit();

            return $this->responseJson(
                true,
                'Commande créée avec succès',
                $commande->load('lignes.produit'),
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->responseJson(
                false,
                'Erreur lors de la création de la commande',
                ['error' => $e->getMessage()],
                500
            );
        }
    }
}
