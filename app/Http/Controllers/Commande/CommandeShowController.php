<?php

namespace App\Http\Controllers\Commande;

use App\Http\Controllers\Controller;
use App\Models\Commande;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\Request;

class CommandeShowController extends Controller
{
    use JsonResponseTrait;

    /**
     * GET /api/commandes/all?withLivraisons=1
     * Liste des commandes avec lignes (+ quantite_livree agrégée par ligne).
     */
    public function all(Request $request)
    {
        try {
            $withLivraisons = $request->boolean('withLivraisons', true);

            $with = [
                'contact',
                'lignes' => fn ($q) => $q
                    ->with('produit')
                    ->withSum('livraisonLignes as quantite_livree', 'quantite'), // 👈 colonne correcte
            ];

            if ($withLivraisons) {
                $with['livraisons.lignes'] = fn ($q) => $q->with('produit');
            }

            $commandes = Commande::with($with)
                ->orderByDesc('id')
                ->get();

            return $this->responseJson(true, 'Liste des commandes', $commandes);
        } catch (\Throwable $e) {
            return $this->responseJson(false, 'Erreur lors de la récupération des commandes.', [
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/commandes/showByNumero/{numero}?withLivraisons=1
     * Détail par numéro avec lignes (+ quantite_livree agrégée).
     */
    public function showByNumero(Request $request, string $numero)
    {
        try {
            $withLivraisons = $request->boolean('withLivraisons', true);

            $with = [
                'contact',
                'lignes' => fn ($q) => $q
                    ->with('produit')
                    ->withSum('livraisonLignes as quantite_livree', 'quantite'), // 👈 colonne correcte
            ];

            if ($withLivraisons) {
                $with['livraisons.lignes'] = fn ($q) => $q->with('produit');
            }

            $commande = Commande::with($with)
                ->where('numero', $numero)
                ->first();

            if (!$commande) {
                return $this->responseJson(false, 'Commande non trouvée.', null, 404);
            }

            return $this->responseJson(true, 'Commande trouvée', $commande);
        } catch (\Throwable $e) {
            return $this->responseJson(false, 'Erreur lors de la récupération de la commande.', [
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/commandes/show/{id}?withLivraisons=1
     * Détail par ID avec lignes (+ quantite_livree agrégée).
     */
    public function show(Request $request, int $id)
    {
        try {
            $withLivraisons = $request->boolean('withLivraisons', true);

            $with = [
                'contact',
                'lignes' => fn ($q) => $q
                    ->with('produit')
                    ->withSum('livraisonLignes as quantite_livree', 'quantite'), // 👈 colonne correcte
            ];

            if ($withLivraisons) {
                $with['livraisons.lignes'] = fn ($q) => $q->with('produit');
            }

            $commande = Commande::with($with)->find($id);

            if (!$commande) {
                return $this->responseJson(false, 'Commande non trouvée.', null, 404);
            }

            return $this->responseJson(true, 'Commande trouvée', $commande);
        } catch (\Throwable $e) {
            return $this->responseJson(false, 'Erreur lors de la récupération de la commande.', [
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
