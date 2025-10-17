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
            $validated = $this->validateRequest($request);
        } catch (ValidationException $e) {
            return $this->responseJson(false, 'Données invalides', $e->errors(), 422);
        }

        DB::beginTransaction();

        try {
            $montantTotal = $this->calculateMontantTotal($validated['lignes'], $validated['reduction'] ?? 0);

            $commande = Commande::create([
                'numero'        => '',
                'vehicule_id'   => $validated['vehicule_id'], // ⬅️
                'montant_total' => $montantTotal,
                'reduction'     => $validated['reduction'] ?? 0,
                'statut'        => 'brouillon',
            ]);

            $commande->update([
                'numero' => $this->generateNumeroCommande($commande->id)
            ]);

            $this->createCommandeLignes($commande->id, $validated['lignes']);

            DB::commit();

            return $this->responseJson(
                true,
                'Commande créée avec succès',
                $commande->load(['lignes.produit', 'vehicule']), // ⬅️
                201
            );

        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return $this->responseJson(false, 'Produit introuvable', ['error' => 'Un ou plusieurs produits n\'existent pas'], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Erreur création commande', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data'  => $validated ?? null
            ]);

            return $this->responseJson(false, 'Erreur lors de la création de la commande', [
                'error' => config('app.debug') ? $e->getMessage() : 'Erreur interne'
            ], 500);
        }
    }

    private function validateRequest(Request $request): array
    {
        return $request->validate([
            'vehicule_id'         => 'required|exists:vehicules,id', // ⬅️
            'reduction'           => 'nullable|numeric|min:0|max:999999.99',
            'lignes'              => 'required|array|min:1|max:50',
            'lignes.*.produit_id' => 'required|exists:produits,id',
            'lignes.*.quantite'   => 'required|integer|min:1|max:10000',
            'lignes.*.prix_vente' => 'nullable|numeric|min:0|max:999999.99',
        ], [
            'vehicule_id.required' => 'Le véhicule est requis.',
            'vehicule_id.exists'   => 'Véhicule introuvable.',
        ]);
    }

    private function calculateMontantTotal(array $lignes, float $reduction = 0): float
    {
        $montantTotal = 0;
        $produitIds = collect($lignes)->pluck('produit_id')->unique();
        $produits = Produit::whereIn('id', $produitIds)->get()->keyBy('id');

        foreach ($lignes as $ligne) {
            $produit = $produits->get($ligne['produit_id']);
            if (!$produit) throw new ModelNotFoundException("Produit {$ligne['produit_id']} introuvable");

            $quantite  = (int) $ligne['quantite'];
            $prixVente = isset($ligne['prix_vente']) && is_numeric($ligne['prix_vente'])
                ? (float) $ligne['prix_vente']
                : (float) $produit->prix_vente;

            if ($produit->gestion_stock && $produit->quantite_stock < $quantite) {
                throw new ValidationException(
                    validator([], [])->errors()->add('stock',
                        "Stock insuffisant pour '{$produit->nom}'. Stock: {$produit->quantite_stock}, demandé: {$quantite}")
                );
            }

            $montantTotal += $prixVente * $quantite;
        }

        return max(0, $montantTotal - $reduction);
    }

    private function createCommandeLignes(int $commandeId, array $lignes): void
    {
        $produitIds = collect($lignes)->pluck('produit_id')->unique();
        $produits   = Produit::whereIn('id', $produitIds)->get()->keyBy('id');

        $bulk = [];
        foreach ($lignes as $ligne) {
            $produit  = $produits->get($ligne['produit_id']);
            $quantite = (int) $ligne['quantite'];
            $prixVente = isset($ligne['prix_vente']) && is_numeric($ligne['prix_vente'])
                ? (float) $ligne['prix_vente']
                : (float) $produit->prix_vente;

            $bulk[] = [
                'commande_id'        => $commandeId,
                'produit_id'         => $produit->id,
                'prix_vente'         => $prixVente,
                'quantite_commandee' => $quantite,
                'quantite_restante'  => $quantite,
                'created_at'         => now(),
                'updated_at'         => now(),
            ];

            if ($produit->gestion_stock) {
                $produit->decrement('quantite_stock', $quantite);
            }
        }

        CommandeLigne::insert($bulk);
    }

    private function generateNumeroCommande(int $id): string
    {
        return 'CO' . str_pad($id, 8, '0', STR_PAD_LEFT);
    }

    public function update(Request $request, Commande $commande)
    {
        if (!$commande->peutEtreModifiee()) {
            return $this->responseJson(false, 'Cette commande ne peut plus être modifiée', [
                'statut_actuel' => $commande->statut
            ], 422);
        }

        try {
            $validated = $this->validateRequest($request);
        } catch (ValidationException $e) {
            return $this->responseJson(false, 'Données invalides', $e->errors(), 422);
        }

        DB::beginTransaction();

        try {
            $montantTotal = $this->calculateMontantTotal($validated['lignes'], $validated['reduction'] ?? 0);

            $this->restoreStockFromOldLines($commande);
            $commande->lignes()->delete();

            $commande->update([
                'vehicule_id'   => $validated['vehicule_id'], // ⬅️
                'montant_total' => $montantTotal,
                'reduction'     => $validated['reduction'] ?? 0,
            ]);

            $this->createCommandeLignes($commande->id, $validated['lignes']);

            DB::commit();

            return $this->responseJson(true, 'Commande mise à jour avec succès',
                $commande->fresh()->load(['lignes.produit', 'vehicule']), 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->responseJson(false, 'Erreur lors de la mise à jour', ['error' => $e->getMessage()], 500);
        }
    }

    private function restoreStockFromOldLines(Commande $commande): void
    {
        foreach ($commande->lignes as $ligne) {
            if ($ligne->produit->gestion_stock) {
                $ligne->produit->increment('quantite_stock', $ligne->quantite_commandee);
            }
        }
    }

    public function getStats(Commande $commande)
    {
        $stats = [
            'resume' => [
                'numero'        => $commande->numero,
                'statut'        => $commande->statut,
                'montant_total' => $commande->montant_total,
                'qte_total'     => $commande->qte_total,
                'reduction'     => $commande->reduction,
                'vehicule'      => [
                    'id'             => optional($commande->vehicule)->id,
                    'immatriculation'=> optional($commande->vehicule)->immatriculation,
                    'type'           => optional($commande->vehicule)->type,
                ],
                'nb_produits_differents' => $commande->lignes->count(),
            ],
            'livraison' => [
                'qte_livree'          => $commande->qte_livree,
                'qte_restante'        => $commande->qte_restante,
                'pourcentage_livre'   => $commande->pourcentage_livre,
                'is_entierement_livree'=> $commande->is_entierement_livree,
            ],
            'produits' => $commande->lignes->map(fn ($ligne) => [
                'produit'             => $ligne->produit->nom,
                'quantite_commandee'  => $ligne->quantite_commandee,
                'quantite_restante'   => $ligne->quantite_restante,
                'prix_unitaire'       => $ligne->prix_vente,
                'sous_total'          => $ligne->quantite_commandee * $ligne->prix_vente,
            ]),
        ];

        return $this->responseJson(true, 'Statistiques récupérées', $stats);
    }
}
