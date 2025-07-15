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

class CommandeUpdateController extends Controller
{
    use JsonResponseTrait;

    public function updateByNumero(Request $request, string $numero)
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
                'contact_id.required' => 'Le client est requis.',
                'contact_id.exists' => 'Le client sélectionné est introuvable.',
                'reduction.numeric' => 'La remise doit être un nombre.',
                'reduction.min' => 'La remise doit être positive.',
                'lignes.required' => 'Veuillez ajouter au moins un produit.',
                'lignes.min' => 'La commande doit contenir au moins un produit.',
                'lignes.*.produit_id.required' => 'Le produit est requis.',
                'lignes.*.produit_id.exists' => 'Produit introuvable.',
                'lignes.*.quantite.required' => 'La quantité est requise.',
                'lignes.*.quantite.integer' => 'La quantité doit être un entier.',
                'lignes.*.quantite.min' => 'La quantité doit être au moins égale à 1.',
                'lignes.*.prix_vente.numeric' => 'Le prix de vente doit être un nombre.',
                'lignes.*.prix_vente.min' => 'Le prix de vente doit être supérieur ou égal à 0.',
            ]);
        } catch (ValidationException $e) {
            return $this->responseJson(false, 'Données invalides', $e->errors(), 422);
        }

        DB::beginTransaction();

        try {
            $commande = Commande::where('numero', $numero)->firstOrFail();

            $commande->update([
                'contact_id' => $validated['contact_id'],
                'reduction' => $validated['reduction'] ?? 0,
            ]);

            // Supprimer les anciennes lignes
            $commande->lignes()->delete();

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
                    'commande_id' => $commande->id,
                    'produit_id' => $produit->id,
                    'prix_vente' => $prixVente,
                    'quantite' => $quantite,
                ]);
            }

            $montantFinal = max(0, $total - $commande->reduction);
            $commande->update(['montant_total' => $montantFinal]);

            DB::commit();

            return $this->responseJson(
                true,
                'Commande mise à jour avec succès',
                $commande->load('lignes.produit'),
                200
            );
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return $this->responseJson(false, 'Commande introuvable', [], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->responseJson(
                false,
                'Erreur lors de la mise à jour de la commande',
                ['error' => $e->getMessage()],
                500
            );
        }
    }
}
