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
use Illuminate\Support\Str;

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
            $commande = Commande::where('numero', $numero)->with(['lignes'])->firstOrFail();

            // Vérifier si la commande est livrée (non modifiable)
            // Normalisation: suppression des accents et mise en minuscule pour éviter les variations ('livré', 'livrée', 'LIVRÉE', etc.)
            $statutNormalise = isset($commande->statut)
                ? Str::lower(Str::ascii((string) $commande->statut))
                : '';
            $statutsBloquants = ['livre', 'livree', 'cloture', 'cloturee'];
            if (in_array($statutNormalise, $statutsBloquants, true)) {
                DB::rollBack();
                return $this->responseJson(
                    false,
                    'Cette commande ne peut pas être modifiée car elle est livrée ou clôturée.',
                    [
                        'statut_commande' => $commande->statut,
                        'numero_commande' => $commande->numero
                    ],
                    403
                );
            }

            // Mettre à jour les informations de base de la commande
            $commande->update([
                'contact_id' => $validated['contact_id'],
                'reduction' => $validated['reduction'] ?? 0,
            ]);

            // Supprimer les anciennes lignes de commande
            $commande->lignes()->delete();

            $total = 0;

            // Créer les nouvelles lignes de commande
            foreach ($validated['lignes'] as $ligne) {
                $produit = Produit::findOrFail($ligne['produit_id']);

                $quantite = $ligne['quantite'];
                $prixVente = isset($ligne['prix_vente']) && is_numeric($ligne['prix_vente'])
                    ? floatval($ligne['prix_vente'])
                    : floatval($produit->prix_vente);

                $sousTotal = $prixVente * $quantite;
                $total += $sousTotal;

                // Créer la ligne de commande avec les bonnes propriétés
                CommandeLigne::create([
                    'commande_id' => $commande->id,
                    'produit_id' => $produit->id,
                    'prix_vente' => $prixVente,
                    'quantite_commandee' => $quantite,
                    'quantite_restante' => $quantite, // Initialiser la quantité restante
                ]);
            }

            // Calculer le montant final avec la réduction
            $montantFinal = max(0, $total - ($validated['reduction'] ?? 0));
            $commande->update(['montant_total' => $montantFinal]);

            DB::commit();

            // Recharger la commande avec ses relations pour la réponse
            $commandeUpdated = $commande->fresh(['lignes.produit', 'contact']);

            return $this->responseJson(
                true,
                'Commande mise à jour avec succès',
                $commandeUpdated,
                200
            );

        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return $this->responseJson(
                false, 
                'Commande introuvable', 
                ['numero' => $numero], 
                404
            );
        } catch (\Exception $e) {
            DB::rollBack();
            
            // Log l'erreur pour debug
            \Log::error('Erreur mise à jour commande: ' . $e->getMessage(), [
                'numero' => $numero,
                'trace' => $e->getTraceAsString()
            ]);

            return $this->responseJson(
                false,
                'Erreur lors de la mise à jour de la commande',
                [
                    'error' => $e->getMessage(),
                    'numero' => $numero
                ],
                500
            );
        }
    }
}
