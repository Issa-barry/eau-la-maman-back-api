<?php

namespace App\Http\Controllers\Commande;

use App\Http\Controllers\Controller;
use App\Models\Commande;
use App\Models\Livraison;
use App\Models\LivraisonLigne;
use Illuminate\Support\Facades\DB;
use App\Traits\JsonResponseTrait; // <-- ajout ici

class CommandeValiderController extends Controller
{
    use JsonResponseTrait; // <-- activation du trait

    public function valider($numero)
    {
        $commande = Commande::where('numero', $numero)
            ->with('lignes.produit')
            ->firstOrFail();

        if ($commande->statut !== 'brouillon') {
            return $this->responseJson(false, 'Seules les commandes en brouillon peuvent être validées.', null, 400);
        }

        foreach ($commande->lignes as $ligne) {
            $produit = $ligne->produit;
            $quantite = $ligne->quantite_commandee;

            if (!is_numeric($quantite) || $quantite <= 0) {
                return $this->responseJson(false, "Quantité invalide pour le produit : {$produit->nom}", [
                    'quantite' => $quantite
                ], 400);
            }

            if ($produit->quantite_stock < $quantite) {
                return $this->responseJson(false, "Stock insuffisant pour le produit : {$produit->nom}", null, 400);
            }
        }

        DB::beginTransaction();

        try {
            foreach ($commande->lignes as $ligne) {
                $produit = $ligne->produit;
                $produit->decrement('quantite_stock', $ligne->quantite_commandee);
            }

            $commande->update(['statut' => 'livraison_en_cours']);

            $livraison = Livraison::create([
                'commande_id'    => $commande->id,
                'client_id'      => $commande->contact_id,
                'date_livraison' => now(),
                'statut'         => 'en_attente',
            ]);

            foreach ($commande->lignes as $ligne) {
                LivraisonLigne::create([
                    'livraison_id'   => $livraison->id,
                    'produit_id'     => $ligne->produit_id,
                    'quantite'       => $ligne->quantite_commandee,
                    'montant_payer'  => $ligne->quantite_commandee * $ligne->prix_vente,
                ]);
            }

            DB::commit();

            return $this->responseJson(true, 'Commande validée et livraison générée.', [
                'commande' => $commande->fresh('lignes.produit'),
                'livraison' => $livraison->load('lignes.produit'),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->responseJson(false, 'Erreur lors de la validation de la commande.', [
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
