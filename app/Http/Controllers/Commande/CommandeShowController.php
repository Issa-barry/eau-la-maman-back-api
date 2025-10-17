<?php

namespace App\Http\Controllers\Commande;

use App\Http\Controllers\Controller;
use App\Models\Commande;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\Request;
use Carbon\Carbon;

class CommandeShowController extends Controller
{
    use JsonResponseTrait;

    /**
     * GET /api/commandes
     * ?page=1&per_page=10
     * &withLivraisons=1
     * &search=...
     * &statut=...
     * &periode=aujourdhui|cette_semaine|ce_mois|cette_annee
     * &vehicule_id=...         (alias rétrocompatible: livreur_id)
     * &date_from=YYYY-MM-DD&date_to=YYYY-MM-DD
     * &total_min=...&total_max=...
     * &qte_min=...&qte_max=...
     * &sort=created_at,-montant_total,qte_livree...
     */
    public function index(Request $r)
    {
        try {
            $v = $r->validate([
                'page'           => 'integer|min:1',
                'per_page'       => 'integer|min:1|max:100',
                'withLivraisons' => 'boolean',
                'search'         => 'nullable|string|max:100',
                'statut'         => 'nullable|in:brouillon,livraison_en_cours,livré,cloturé,annulé',
                'vehicule_id'    => 'nullable|integer',
                'livreur_id'     => 'nullable|integer', // alias legacy
                'date_from'      => 'nullable|date',
                'date_to'        => 'nullable|date',
                'periode'        => 'nullable|in:aujourdhui,cette_semaine,ce_mois,cette_annee',
                'total_min'      => 'nullable|numeric|min:0',
                'total_max'      => 'nullable|numeric|min:0',
                'qte_min'        => 'nullable|integer|min:0',
                'qte_max'        => 'nullable|integer|min:0',
                'sort'           => 'nullable|string',
            ]);

            $withLivraisons = $r->boolean('withLivraisons', true);

            $with = [
                'vehicule', // ⬅️ remplace 'contact'
                'lignes' => fn ($q) => $q
                    ->with('produit')
                    ->withSum('livraisonLignes as quantite_livree', 'quantite'),
            ];
            if ($withLivraisons) {
                // conserve le chargement des lignes de chaque livraison
                $with['livraisons.lignes'] = fn ($q) => $q->with('produit');
            }

            $q = Commande::query()
                ->with($with)
                ->withSum('lignes as qte_total', 'quantite_commandee')
                ->withSum('livraisonLignes as qte_livree', 'quantite');

            // 🔹 Filtres
            if (!empty($v['statut']))     $q->where('statut', $v['statut']);

            // support legacy: si livreur_id fourni, on le traite comme vehicule_id
            $vehiculeId = $v['vehicule_id'] ?? $v['livreur_id'] ?? null;
            if (!empty($vehiculeId)) $q->where('vehicule_id', $vehiculeId);

            if (!empty($v['date_from']))  $q->whereDate('created_at', '>=', $v['date_from']);
            if (!empty($v['date_to']))    $q->whereDate('created_at', '<=', $v['date_to']);
            if (!empty($v['total_min']))  $q->where('montant_total', '>=', $v['total_min']);
            if (!empty($v['total_max']))  $q->where('montant_total', '<=', $v['total_max']);

            // 🔹 Période
            if (!empty($v['periode'])) {
                switch ($v['periode']) {
                    case 'aujourdhui':
                        $q->whereDate('created_at', Carbon::today());
                        break;
                    case 'cette_semaine':
                        $q->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
                        break;
                    case 'ce_mois':
                        $q->whereYear('created_at', Carbon::now()->year)
                          ->whereMonth('created_at', Carbon::now()->month);
                        break;
                    case 'cette_annee':
                        $q->whereYear('created_at', Carbon::now()->year);
                        break;
                }
            }

            // 🔹 Recherche globale (numéro + véhicule)
            if (!empty($v['search'])) {
                $s = trim($v['search']);
                $q->where(function ($qq) use ($s) {
                    $qq->where('numero', 'like', "%$s%")
                       ->orWhereHas('vehicule', function ($vq) use ($s) {
                           $vq->where('immatriculation', 'like', "%$s%")
                              ->orWhere('type', 'like', "%$s%")
                              ->orWhere('nom', 'like', "%$s%")
                              ->orWhere('nom_proprietaire', 'like', "%$s%")
                              ->orWhere('prenom_proprietaire', 'like', "%$s%");
                       });
                });
            }

            // 🔹 Filtres HAVING
            if (isset($v['qte_min'])) $q->having('qte_livree', '>=', $v['qte_min']);
            if (isset($v['qte_max'])) $q->having('qte_livree', '<=', $v['qte_max']);

            // 🔹 Tri
            $sortable = ['created_at','montant_total','numero','qte_livree','qte_total','statut'];
            if (!empty($v['sort'])) {
                foreach (explode(',', $v['sort']) as $piece) {
                    $dir = str_starts_with($piece, '-') ? 'desc' : 'asc';
                    $col = ltrim($piece, '-');
                    if (in_array($col, $sortable, true)) {
                        $q->orderBy($col, $dir);
                    }
                }
            } else {
                $q->orderBy('created_at', 'desc');
            }

            $perPage   = $v['per_page'] ?? 10;
            $paginator = $q->paginate($perPage)->appends($r->query());

            return $this->responseJson(true, 'Liste des commandes', [
                'data' => $paginator->items(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page'     => $paginator->perPage(),
                    'total'        => $paginator->total(),
                    'last_page'    => $paginator->lastPage(),
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->responseJson(false, 'Erreur lors de la récupération des commandes.', [
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/commandes/showByNumero/{numero}?withLivraisons=1
     */
    public function showByNumero(Request $request, string $numero)
    {
        try {
            $withLivraisons = $request->boolean('withLivraisons', true);

            $with = [
                'vehicule', // ⬅️ remplace 'contact'
                'lignes' => fn ($q) => $q
                    ->with('produit')
                    ->withSum('livraisonLignes as quantite_livree', 'quantite'),
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
}
