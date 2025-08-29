<?php
namespace App\Http\Controllers\Livraison;

use App\Http\Controllers\Controller;
use App\Models\Commande;
use App\Models\FactureLivraison;
use App\Models\Livraison;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LivraisonValidationController extends Controller
{
    use JsonResponseTrait;

    public function valider(Request $request)
    {
        try {
            $validated = $request->validate([
                'commande_numero' => 'required|exists:commandes,numero',
                'client_id'       => 'required|exists:users,id',
                'date_livraison'  => 'required|date',
                'produits'        => 'required|array|min:1',
                'produits.*.produit_id' => 'required|exists:produits,id',
                'produits.*.quantite'   => 'required|integer|min:0',
            ]);
        } catch (ValidationException $e) {
            return $this->responseJson(false, 'Données invalides.', $e->errors(), 422);
        }

        DB::beginTransaction();

        try {
            $commande = Commande::with('lignes')->where('numero', $validated['commande_numero'])->firstOrFail();

            // Vérifier si la commande est déjà totalement livrée
            $resteTotal = $commande->lignes->sum('quantite_restante');
            if ($resteTotal === 0) {
                return $this->responseJson(false, 'Cette commande a déjà été totalement livrée.', null, 422);
            }

            $produitsLivrables = [];
            $erreurs = [];

            foreach ($validated['produits'] as $item) {
                $produitId = $item['produit_id'];
                $quantite = $item['quantite'];

                $ligne = $commande->lignes->firstWhere('produit_id', $produitId);

                if (!$ligne) {
                    $erreurs[] = "Le produit ID {$produitId} ne fait pas partie de la commande.";
                    continue;
                }

                if ($ligne->quantite_restante <= 0) {
                    $erreurs[] = "Le produit ID {$produitId} est déjà totalement livré.";
                    continue;
                }

                if ($quantite > $ligne->quantite_restante) {
                    $erreurs[] = "Quantité à livrer ($quantite) pour le produit ID {$produitId} dépasse le reste à livrer ({$ligne->quantite_restante}).";
                    continue;
                }

                if ($quantite > 0) {
                    $produitsLivrables[] = [
                        'produit_id' => $produitId,
                        'quantite'   => $quantite,
                        'prix_vente' => $ligne->prix_vente,
                        'ligne'      => $ligne
                    ];
                }
            }

            if (count($produitsLivrables) === 0) {
    $produitsEncoreLivrables = $commande->lignes->filter(fn($l) => $l->quantite_restante > 0);

    if ($produitsEncoreLivrables->count() > 0) {
        return $this->responseJson(false,
            'Les produits sélectionnés sont déjà totalement livrés. Veuillez sélectionner un produit restant à livrer.',
            $erreurs,
            422
        );
    }

    return $this->responseJson(false,
        'Cette commande a déjà été totalement livrée.',
        null,
        422
    );
}


            $livraison = Livraison::create([
                'commande_id'    => $commande->id,
                'client_id'      => $validated['client_id'],
                'date_livraison' => $validated['date_livraison'],
                'statut'         => 'livré',
            ]);

            $montantTotal = 0;

            foreach ($produitsLivrables as $item) {
                $montant = $item['quantite'] * $item['prix_vente'];
                $montantTotal += $montant;

                $livraison->lignes()->create([
                    'produit_id'    => $item['produit_id'],
                    'quantite'      => $item['quantite'],
                    'montant_payer' => $montant,
                ]);

                // Mise à jour de la quantité restante
                $item['ligne']->decrement('quantite_restante', $item['quantite']);
            }

            // Génération de la facture
            $date = now()->format('Ymd');
            $lastId = FactureLivraison::max('id') + 1;
            $numero = 'FAC-' . $date . '-' . str_pad($lastId, 4, '0', STR_PAD_LEFT);

            $facture = FactureLivraison::create([
                'numero'       => $numero,
                'client_id'    => $validated['client_id'],
                'livraison_id' => $livraison->id,
                'total'        => $montantTotal,
                'montant_du'   => $montantTotal,
                'statut'       => FactureLivraison::STATUT_BROUILLON,
            ]);

            // Mise à jour statut commande
            $resteApresLivraison = $commande->lignes()->sum('quantite_restante');
            $nouveauStatut = $resteApresLivraison === 0 ? 'livré' : 'livraison_en_cours';

            $commande->update(['statut' => $nouveauStatut]);

            DB::commit();

            return $this->responseJson(true, 'Livraison validée avec succès.', [
                'livraison' => $livraison->load('lignes.produit'),
                'facture'   => $facture,
                'commande_statut' => $nouveauStatut,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return $this->responseJson(false, 'Erreur serveur.', [
                'message' => config('app.debug') ? $e->getMessage() : 'Erreur lors de la validation de la livraison.'
            ], 500);
        }
    }
}
