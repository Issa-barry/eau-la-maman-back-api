<?php

namespace App\Http\Controllers\Commande;

use App\Http\Controllers\Controller;
use App\Models\Commande;
use Illuminate\Support\Facades\DB;
use App\Traits\JsonResponseTrait;

class CommandeValiderController extends Controller
{
    use JsonResponseTrait;

    /**
     * Valide la commande (contrôles préalables),
     * bascule la commande en "livraison_en_cours".
     */
    public function valider($numero)
    {
        // Récupérer la commande
        $commande = Commande::where('numero', $numero)
            ->with('lignes.produit')
            ->firstOrFail();

        // Vérifier si la commande est en statut "brouillon"
        if ($commande->statut !== 'brouillon') {
            return $this->responseJson(false, 'Seules les commandes en brouillon peuvent être validées.', null, 400);
        }

        // 1) Contrôles préalables quantités/stock
        foreach ($commande->lignes as $ligne) {
            $qte = (float) ($ligne->quantite_chargee ?? $ligne->quantite_commandee);
            $produit = $ligne->produit;

            // Vérifier la quantité et le stock
            if (!is_numeric($qte) || $qte <= 0) {
                return $this->responseJson(false, "Quantité invalide pour le produit : {$produit->nom}", [
                    'quantite' => $qte
                ], 400);
            }
            if ($produit->quantite_stock < $qte) {
                return $this->responseJson(false, "Stock insuffisant pour le produit : {$produit->nom}", null, 400);
            }
        }

        DB::beginTransaction();

        try {
            // 2) Décrément stock sur la quantité réellement chargée
            foreach ($commande->lignes as $ligne) {
                $qte = (float) ($ligne->quantite_chargee ?? $ligne->quantite_commandee);
                if ($qte > 0) {
                    $ligne->produit->decrement('quantite_stock', $qte);
                }
            }

            // 3) Mettre à jour le statut de la commande à "livraison_en_cours"
            $commande->update(['statut' => 'livraison_en_cours']);

            DB::commit();

            return $this->responseJson(true, 'Commande validée avec succès et statut mis à jour.', [
                'commande' => $commande->fresh('lignes.produit'),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->responseJson(false, 'Erreur lors de la validation de la commande.', [
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
