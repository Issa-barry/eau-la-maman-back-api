<?php

namespace App\Http\Controllers\Commande;

use App\Http\Controllers\Controller;
use App\Models\Commande;
use App\Models\CommandeLigne;
use App\Models\FactureLivraison;
use App\Models\FactureLigne;
use App\Models\Produit;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException; 
use Throwable;

class CommandeFactureImediaController extends Controller
{
    use JsonResponseTrait;

    /**
     * Crée une commande + lignes, passe le statut à "livraison_en_cours"
     * et génère automatiquement la facture + lignes de facture.
     *
     * POST /commandes/imedia
     *
     * Body attendu:
     * {
     *   "vehicule_id": 1,
     *   "reduction": 0,                // optionnel
     *   "tva": 0.18,                   // optionnel (par défaut 0.18 si non fourni)
     *   "lignes": [
     *     { "produit_id": 10, "quantite": 5, "prix_vente": 1200.00 },
     *     { "produit_id": 12, "quantite": 2, "prix_vente": 3500.00 }
     *   ]
     * }
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'vehicule_id'        => 'required|integer|exists:vehicules,id',
                'reduction'          => 'nullable|numeric|min:0',
                'tva'                => 'nullable|numeric|min:0', // ex: 0.18
                'lignes'             => 'required|array|min:1',
                'lignes.*.produit_id'=> 'required|integer|exists:produits,id',
                'lignes.*.quantite'  => 'required|integer|min:1',
                'lignes.*.prix_vente'=> 'required|numeric|min:0',
            ]);
        } catch (ValidationException $e) {
            return $this->responseJson(false, 'Données invalides', $e->errors(), 422);
        }

        $tva = array_key_exists('tva', $validated)
            ? (float) $validated['tva']
            : (float) (config('app.tva', 0.18)); // défaut: 18%

        $reduction = (float) ($validated['reduction'] ?? 0);

        DB::beginTransaction();

        try {
            // 1) Créer la commande (statut directement "livraison_en_cours")
            $montantBrut = 0.0;
            $qteTotal    = 0;

            foreach ($validated['lignes'] as $l) {
                $montantBrut += ((float)$l['prix_vente']) * ((int)$l['quantite']);
                $qteTotal    += (int)$l['quantite'];
            }

            $montantNet = max(0, $montantBrut - $reduction);

            /** @var Commande $commande */
            $commande = Commande::create([
                'numero'        => '', // sera auto-généré par le model boot()
                'vehicule_id'   => $validated['vehicule_id'],
                'montant_total' => $montantNet,
                'reduction'     => $reduction,
                'statut'        => 'livraison_en_cours',
            ]);

            // Lignes de commande
            $commandeLignes = [];
            foreach ($validated['lignes'] as $l) {
                $commandeLignes[] = new CommandeLigne([
                    'produit_id'          => $l['produit_id'],
                    'prix_vente'          => $l['prix_vente'],
                    'quantite_commandee'  => $l['quantite'],
                    'quantite_restante'   => $l['quantite'], // au départ tout reste à livrer
                ]);
            }
            $commande->lignes()->saveMany($commandeLignes);

            // 2) Générer la facture liée
            // On considère "total" = TOTAL_TTC (plus simple avec ton modèle actuel).
            $totalHt  = 0.0;
            $totalTtc = 0.0;

            /** @var FactureLivraison $facture */
            $facture = FactureLivraison::create([
                'commande_id' => $commande->id,
                'numero'      => $this->generateFactureNumero(),
                'montant_du'  => 0,    // sera rafraîchi après création des lignes (via total)
                'total'       => 0,    // idem, on mettra le TTC final
                'statut'      => FactureLivraison::STATUT_IMPAYE, // directement impayé
            ]);

            $factureLignes = [];
            foreach ($commande->lignes as $cl) {
                // On récupère éventuellement le produit pour cohérence (nom, etc.)
                /** @var Produit $produit */
                $produit = Produit::find($cl->produit_id);

                $prixUnitaireHt = (float) $cl->prix_vente; // prix_vente considéré comme HT
                $qte            = (int) $cl->quantite_commandee;

                $montantHt  = round($prixUnitaireHt * $qte, 2);
                $montantTtc = round($montantHt * (1 + $tva), 2);

                $totalHt  += $montantHt;
                $totalTtc += $montantTtc;

                $factureLignes[] = new FactureLigne([
                    'produit_id'        => $cl->produit_id,
                    'quantite'          => $qte,
                    'prix_unitaire_ht'  => $prixUnitaireHt,
                    'montant_ht'        => $montantHt,
                    'montant_ttc'       => $montantTtc,
                ]);
            }

            // Appliquer la réduction au prorata sur le HT puis recalcul TTC si tu préfères.
            // Ici, on applique la réduction sur le TOTAL TTC pour rester simple et coller à "montant_total".
            if ($reduction > 0) {
                $totalTtc = max(0, round($totalTtc - $reduction, 2));

                // Optionnel: répartir la réduction au prorata sur chaque ligne TTC.
                // (Si nécessaire, ajoute ici une passe pour ajuster $factureLignes ligne par ligne.)
            }

            $facture->lignes()->saveMany($factureLignes);

            // Mettre à jour les totaux de facture
            $facture->total      = $totalTtc;          // on stocke TTC dans "total"
            $facture->montant_du = $totalTtc;          // aucun encaissement pour l'instant
            $facture->save();

            DB::commit();

            // Recharger proprement avec relations utiles
            $commande->load(['vehicule', 'lignes.produit']);
            $facture->load(['commande.vehicule', 'lignes.produit', 'encaissements']);

            return $this->responseJson(true, 'Commande et facture créées', [
                'commande' => $commande,
                'facture'  => $facture,
            ], 201);

        } catch (Throwable $e) {
            DB::rollBack();
            report($e);
            return $this->responseJson(false, 'Erreur serveur', [
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Génère un numéro de facture de type FA00000042
     */
    private function generateFactureNumero(): string
    {
        $last = FactureLivraison::latest('id')->first();
        $next = $last ? $last->id + 1 : 1;
        return 'FA' . str_pad((string)$next, 8, '0', STR_PAD_LEFT);
    }
}
