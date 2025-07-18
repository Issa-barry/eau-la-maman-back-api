<?php

namespace App\Http\Controllers\Livraison;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Commande;
use App\Models\Livraison;
use App\Models\Produit;
use App\Models\FactureLivraison;
use App\Traits\JsonResponseTrait;
use Illuminate\Validation\ValidationException;
use Throwable;

class LivraisonValidationController extends Controller
{
    use JsonResponseTrait;

    /**
     * Valider une livraison depuis une commande et générer une facture.
     */
    public function valider(Request $request)
    {
        try {
            $validated = $request->validate([
                'commande_numero' => 'required|exists:commandes,numero',
                'client_id' => 'required|exists:users,id',
                'date_livraison' => 'required|date',
                'produits' => 'required|array|min:1',
                'produits.*.produit_id' => 'required|exists:produits,id',
                'produits.*.quantite' => 'required|integer|min:1',
            ]);
        } catch (ValidationException $e) {
            return $this->responseJson(false, 'Erreur de validation.', [
                'errors' => $e->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $commande = Commande::with(['contact', 'lignes', 'livraisons.lignes'])
                ->where('numero', $validated['commande_numero'])
                ->firstOrFail();

            $produitsCommande = $commande->lignes->pluck('produit_id')->toArray();
            $quantitesCommandees = $commande->lignes->pluck('quantite', 'produit_id')->toArray();

            // Quantités déjà livrées par produit
            $quantitesLivrees = [];
            foreach ($commande->livraisons as $liv) {
                foreach ($liv->lignes as $ligneLivree) {
                    $pid = $ligneLivree->produit_id;
                    $quantitesLivrees[$pid] = ($quantitesLivrees[$pid] ?? 0) + $ligneLivree->quantite;
                }
            }

            $erreurs = [];

            foreach ($validated['produits'] as $i => $item) {
                $produitId = $item['produit_id'];
                $quantiteLivree = $item['quantite'];
                $quantiteCommandee = $quantitesCommandees[$produitId] ?? 0;
                $quantiteDejaLivree = $quantitesLivrees[$produitId] ?? 0;
                $quantiteRestante = $quantiteCommandee - $quantiteDejaLivree;

                if (!in_array($produitId, $produitsCommande)) {
                    $erreurs[] = [
                        'index' => $i,
                        'produit_id' => $produitId,
                        'erreur' => 'Le produit ne fait pas partie de la commande.'
                    ];
                } elseif ($quantiteLivree > $quantiteRestante) {
                    $erreurs[] = [
                        'index' => $i,
                        'produit_id' => $produitId,
                        'quantite_commandee' => $quantiteCommandee,
                        'quantite_deja_livree' => $quantiteDejaLivree,
                        'quantite_restant' => $quantiteRestante,
                        'quantite_saisie' => $quantiteLivree,
                        'erreur' => 'Quantité livrée dépasse le restant à livrer.'
                    ];
                }
            }

            if (!empty($erreurs)) {
                return $this->responseJson(false, 'Erreurs détectées dans les produits livrés.', $erreurs, 422);
            }

            $livraison = Livraison::create([
                'commande_id'    => $commande->id,
                'client_id'      => $validated['client_id'],
                'date_livraison' => $validated['date_livraison'],
                'statut'         => 'livré',
            ]);

            $total = 0;

            foreach ($validated['produits'] as $item) {
                $ligneCommande = $commande->lignes->firstWhere('produit_id', $item['produit_id']);

                if (!$ligneCommande) {
                    throw new \Exception("Produit ID {$item['produit_id']} non trouvé dans la commande.");
                }

                $prixVente = $ligneCommande->prix_vente;
                $montant = $prixVente * $item['quantite'];
                $total += $montant;

                $livraison->lignes()->create([
                    'produit_id'    => $item['produit_id'],
                    'quantite'      => $item['quantite'],
                    'montant_payer' => $montant,
                ]);
            }

            $date = now()->format('Ymd');
            $lastId = FactureLivraison::max('id') + 1;
            $numero = 'FAC-' . $date . '-' . str_pad($lastId, 4, '0', STR_PAD_LEFT);

            $facture = FactureLivraison::create([
                'numero'        => $numero,
                'client_id'     => $validated['client_id'],
                'livraison_id'  => $livraison->id,
                'total'         => $total,
                'montant_du'    => $total,
                'statut'        => FactureLivraison::STATUT_BROUILLON,
            ]);

            DB::commit();

            return $this->responseJson(true, 'Livraison validée et facture générée avec succès.', [
                'livraison' => $livraison->load(['client', 'commande.contact', 'lignes.produit']),
                'facture'   => $facture,
            ]);
        } catch (Throwable $e) {
            DB::rollBack();

            return $this->responseJson(false, 'Erreur serveur lors de la validation de la livraison.', [
                'error' => app()->environment('local') || config('app.debug') ? $e->getMessage() : 'Erreur interne'
            ], 500);
        }
    }
}
