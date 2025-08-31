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
            return $this->responseJson(
                false,
                'Données invalides',
                $e->errors(),
                422
            );
        }

        DB::beginTransaction();

        try {
            // Créer la commande (qte_total sera calculé automatiquement par l'accesseur)
            $montantTotal = $this->calculateMontantTotal($validated['lignes'], $validated['reduction'] ?? 0);
            
            $commande = Commande::create([
                'numero' => '', // Sera mis à jour après
                'contact_id' => $validated['contact_id'],
                'montant_total' => $montantTotal,
                'reduction' => $validated['reduction'] ?? 0,
                'statut' => 'brouillon',
            ]);

            // Générer le numéro de commande
            $commande->update([
                'numero' => $this->generateNumeroCommande($commande->id)
            ]);

            // Créer les lignes de commande
            $this->createCommandeLignes($commande->id, $validated['lignes']);

            DB::commit();

            return $this->responseJson(
                true,
                'Commande créée avec succès',
                $commande->load(['lignes.produit', 'contact']),
                201
            );

        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return $this->responseJson(
                false,
                'Produit introuvable',
                ['error' => 'Un ou plusieurs produits n\'existent pas'],
                404
            );

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Erreur création commande', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $validated ?? null
            ]);

            return $this->responseJson(
                false,
                'Erreur lors de la création de la commande',
                ['error' => config('app.debug') ? $e->getMessage() : 'Erreur interne'],
                500
            );
        }
    }

    /**
     * Validation des données de la requête
     */
    private function validateRequest(Request $request): array
    {
        return $request->validate([
            'contact_id' => 'required|exists:users,id',
            'reduction' => 'nullable|numeric|min:0|max:999999.99',
            'lignes' => 'required|array|min:1|max:50', // Limite pour éviter les abus
            'lignes.*.produit_id' => 'required|exists:produits,id',
            'lignes.*.quantite' => 'required|integer|min:1|max:10000',
            'lignes.*.prix_vente' => 'nullable|numeric|min:0|max:999999.99',
        ], [
            'contact_id.required' => 'Le contact est requis.',
            'contact_id.exists' => 'Contact introuvable.',
            'reduction.numeric' => 'La réduction doit être un nombre.',
            'reduction.min' => 'La réduction ne peut pas être négative.',
            'reduction.max' => 'La réduction est trop élevée.',
            
            'lignes.required' => 'La commande doit contenir au moins un produit.',
            'lignes.min' => 'La commande doit contenir au moins un produit.',
            'lignes.max' => 'Trop de produits dans cette commande (maximum 50).',
            
            'lignes.*.produit_id.required' => 'Produit requis pour chaque ligne.',
            'lignes.*.produit_id.exists' => 'Un ou plusieurs produits sont introuvables.',
            
            'lignes.*.quantite.required' => 'Quantité requise pour chaque produit.',
            'lignes.*.quantite.integer' => 'La quantité doit être un nombre entier.',
            'lignes.*.quantite.min' => 'La quantité doit être d\'au moins 1.',
            'lignes.*.quantite.max' => 'Quantité trop élevée pour un produit.',
            
            'lignes.*.prix_vente.numeric' => 'Le prix de vente doit être un nombre.',
            'lignes.*.prix_vente.min' => 'Le prix de vente ne peut pas être négatif.',
            'lignes.*.prix_vente.max' => 'Le prix de vente est trop élevé.',
        ]);
    }

    /**
     * Calcule le montant total de la commande
     */
    private function calculateMontantTotal(array $lignes, float $reduction = 0): float
    {
        $montantTotal = 0;
        
        // Récupérer tous les produits en une seule requête pour optimiser
        $produitIds = collect($lignes)->pluck('produit_id')->unique();
        $produits = Produit::whereIn('id', $produitIds)->get()->keyBy('id');
        
        foreach ($lignes as $ligne) {
            $produit = $produits->get($ligne['produit_id']);
            
            if (!$produit) {
                throw new ModelNotFoundException("Produit {$ligne['produit_id']} introuvable");
            }

            $quantite = (int) $ligne['quantite'];
            $prixVente = isset($ligne['prix_vente']) && is_numeric($ligne['prix_vente'])
                ? (float) $ligne['prix_vente']
                : (float) $produit->prix_vente;

            // Vérification des stocks si gestion activée
            if ($produit->gestion_stock && $produit->quantite_stock < $quantite) {
                throw new ValidationException(
                    validator([], [])->errors()->add(
                        'stock', 
                        "Stock insuffisant pour le produit '{$produit->nom}'. Stock disponible: {$produit->quantite_stock}, demandé: {$quantite}"
                    )
                );
            }

            $sousTotal = $prixVente * $quantite;
            $montantTotal += $sousTotal;
        }

        return max(0, $montantTotal - $reduction);
    }

    /**
     * Crée les lignes de commande
     */
    private function createCommandeLignes(int $commandeId, array $lignes): void
    {
        $produitIds = collect($lignes)->pluck('produit_id')->unique();
        $produits = Produit::whereIn('id', $produitIds)->get()->keyBy('id');

        $lignesData = [];
        
        foreach ($lignes as $ligne) {
            $produit = $produits->get($ligne['produit_id']);
            $quantite = (int) $ligne['quantite'];
            
            $prixVente = isset($ligne['prix_vente']) && is_numeric($ligne['prix_vente'])
                ? (float) $ligne['prix_vente']
                : (float) $produit->prix_vente;

            $lignesData[] = [
                'commande_id' => $commandeId,
                'produit_id' => $produit->id,
                'prix_vente' => $prixVente,
                'quantite_commandee' => $quantite,
                'quantite_restante' => $quantite,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Décrémenter le stock si gestion activée
            if ($produit->gestion_stock) {
                $produit->decrement('quantite_stock', $quantite);
            }
        }

        // Insertion en lot pour optimiser les performances
        CommandeLigne::insert($lignesData);
    }

    /**
     * Génère un numéro de commande unique
     */
    private function generateNumeroCommande(int $id): string
    {
        return 'CO' . str_pad($id, 8, '0', STR_PAD_LEFT);
    }

    /**
     * Met à jour une commande existante
     */
    public function update(Request $request, Commande $commande)
    {
        if (!in_array($commande->statut, ['brouillon'])) {
            return $this->responseJson(
                false,
                'Cette commande ne peut plus être modifiée',
                ['statut_actuel' => $commande->statut],
                422
            );
        }

        try {
            $validated = $this->validateRequest($request);
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
            // Recalculer le montant total
            $montantTotal = $this->calculateMontantTotal($validated['lignes'], $validated['reduction'] ?? 0);
            
            // Restaurer les stocks des anciennes lignes
            $this->restoreStockFromOldLines($commande);
            
            // Supprimer les anciennes lignes
            $commande->lignes()->delete();
            
            // Mettre à jour la commande
            $commande->update([
                'contact_id' => $validated['contact_id'],
                'montant_total' => $montantTotal,
                'reduction' => $validated['reduction'] ?? 0,
            ]);

            // Créer les nouvelles lignes
            $this->createCommandeLignes($commande->id, $validated['lignes']);

            DB::commit();

            return $this->responseJson(
                true,
                'Commande mise à jour avec succès',
                $commande->fresh()->load(['lignes.produit', 'contact']),
                200
            );

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->responseJson(
                false,
                'Erreur lors de la mise à jour',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Restaure les stocks des anciennes lignes
     */
    private function restoreStockFromOldLines(Commande $commande): void
    {
        foreach ($commande->lignes as $ligne) {
            if ($ligne->produit->gestion_stock) {
                $ligne->produit->increment('quantite_stock', $ligne->quantite_commandee);
            }
        }
    }

    /**
     * Récupère les statistiques d'une commande
     */
    public function getStats(Commande $commande)
    {
        $stats = [
            'resume' => [
                'numero' => $commande->numero,
                'statut' => $commande->statut,
                'montant_total' => $commande->montant_total,
                'qte_total' => $commande->qte_total, // Accesseur du modèle
                'reduction' => $commande->reduction,
                'nb_produits_differents' => $commande->lignes->count(),
            ],
            'livraison' => [
                'qte_livree' => $commande->qte_livree, // Accesseur du modèle
                'qte_restante' => $commande->qte_restante, // Accesseur du modèle
                'pourcentage_livre' => $commande->pourcentage_livre, // Accesseur du modèle
                'is_entierement_livree' => $commande->is_entierement_livree, // Accesseur du modèle
            ],
            'produits' => $commande->lignes->map(function ($ligne) {
                return [
                    'produit' => $ligne->produit->nom,
                    'quantite_commandee' => $ligne->quantite_commandee,
                    'quantite_restante' => $ligne->quantite_restante,
                    'prix_unitaire' => $ligne->prix_vente,
                    'sous_total' => $ligne->quantite_commandee * $ligne->prix_vente
                ];
            })
        ];

        return $this->responseJson(true, 'Statistiques récupérées', $stats);
    }
}