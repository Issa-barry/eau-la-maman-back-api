<?php

namespace App\Http\Controllers\Livraison;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Livraison;
use App\Models\Produit;
use App\Traits\JsonResponseTrait;
use Illuminate\Validation\ValidationException;

class LivraisonUpdateController extends Controller
{
    use JsonResponseTrait;

    /**
     * Modifier une livraison existante.
     */
    public function update(Request $request, int $id)
    {
        try {
            $validated = $request->validate([
                'client_id' => 'required|exists:users,id',
                'date_livraison' => 'required|date',
                'statut' => 'required|in:en_cours,livr\u00e9,en_attente,annul\u00e9',
                'produits' => 'required|array|min:1',
                'produits.*.produit_id' => 'required|exists:produits,id',
                'produits.*.quantite' => 'required|integer|min:1',
            ], [
                'client_id.exists' => 'Le client s\u00e9lectionn\u00e9 est introuvable.',
                'produits.*.produit_id.exists' => 'Un des produits s\u00e9lectionn\u00e9s est invalide.',
                'produits.*.quantite.min' => 'La quantit\u00e9 doit \u00eatre sup\u00e9rieure \u00e0 z\u00e9ro.',
            ]);

            DB::beginTransaction();

            $livraison = Livraison::with(['lignes', 'commande.lignes', 'commande.livraisons.lignes'])->find($id);

            if (!$livraison) {
                return $this->responseJson(false, 'Livraison introuvable.', null, 404);
            }

            $commande = $livraison->commande;
            $quantitesCommandees = $commande->lignes->pluck('quantite', 'produit_id')->toArray();

            $quantitesLivrees = [];
            foreach ($commande->livraisons as $liv) {
                if ($liv->id === $livraison->id) continue;
                foreach ($liv->lignes as $ligne) {
                    $pid = $ligne->produit_id;
                    $quantitesLivrees[$pid] = ($quantitesLivrees[$pid] ?? 0) + $ligne->quantite;
                }
            }

            $erreurs = [];
            foreach ($validated['produits'] as $i => $item) {
                $produitId = $item['produit_id'];
                $quantiteLivree = $item['quantite'];
                $quantiteCommandee = $quantitesCommandees[$produitId] ?? 0;
                $quantiteDejaLivree = $quantitesLivrees[$produitId] ?? 0;
                $quantiteRestante = $quantiteCommandee - $quantiteDejaLivree;

                if ($quantiteLivree > $quantiteRestante) {
                    $erreurs[] = [
                        'index' => $i,
                        'produit_id' => $produitId,
                        'quantite_commandee' => $quantiteCommandee,
                        'quantite_deja_livree' => $quantiteDejaLivree,
                        'quantite_restant' => $quantiteRestante,
                        'quantite_saisie' => $quantiteLivree,
                        'erreur' => 'Quantit\u00e9 livr\u00e9e d\u00e9passe le restant \u00e0 livrer.'
                    ];
                }
            }

            if (!empty($erreurs)) {
                return $this->responseJson(false, 'Erreurs d\u00e9tect\u00e9es dans les produits livr\u00e9s.', $erreurs, 422);
            }

            $livraison->update([
                'client_id' => $validated['client_id'],
                'date_livraison' => $validated['date_livraison'],
                'statut' => $validated['statut'],
            ]);

            $livraison->lignes()->delete();
            foreach ($validated['produits'] as $item) {
                $produit = Produit::findOrFail($item['produit_id']);
                $montant = $produit->prix_vente * $item['quantite'];
                $livraison->lignes()->create([
                    'produit_id' => $produit->id,
                    'quantite' => $item['quantite'],
                    'montant_payer' => $montant,
                ]);
            }

            DB::commit();

            return $this->responseJson(true, 'Livraison mise \u00e0 jour avec succ\u00e8s.', $livraison->load([
                'client',
                'commande.contact',
                'lignes.produit'
            ]));
        } catch (ValidationException $e) {
            return $this->responseJson(false, 'Erreur de validation.', [
                'message' => 'Certains champs sont invalides. Veuillez corriger les erreurs ci-dessous.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->responseJson(false, 'Erreur serveur lors de la mise \u00e0 jour.', [
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
