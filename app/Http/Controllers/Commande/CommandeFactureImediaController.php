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
     * et génère automatiquement la facture + lignes correspondantes (sans TVA).
     *
     * POST /commandes/imedia
     *
     * Body attendu :
     * {
     *   "vehicule_id": 1,
     *   "reduction": 0,
     *   "lignes": [
     *     { "produit_id": 1, "quantite": 2, "prix_vente": 5000 }
     *   ]
     * }
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'vehicule_id'         => 'required|integer|exists:vehicules,id',
                'reduction'           => 'nullable|numeric|min:0',
                'lignes'              => 'required|array|min:1',
                'lignes.*.produit_id' => 'required|integer|exists:produits,id',
                'lignes.*.quantite'   => 'required|integer|min:1',
                'lignes.*.prix_vente' => 'required|numeric|min:0',
            ]);
        } catch (ValidationException $e) {
            return $this->responseJson(false, 'Données invalides', $e->errors(), 422);
        }

        $reduction = (float) ($validated['reduction'] ?? 0);

        DB::beginTransaction();

        try {
            // === 1) Créer la commande ===
            $montantBrut = 0.0;
            $qteTotal    = 0;

            foreach ($validated['lignes'] as $l) {
                $montantBrut += ((float) $l['prix_vente']) * ((int) $l['quantite']);
                $qteTotal    += (int) $l['quantite'];
            }

            $montantNet = max(0, $montantBrut - $reduction);

            /** @var Commande $commande */
            $commande = Commande::create([
                'numero'        => '', // généré automatiquement par le modèle
                'vehicule_id'   => $validated['vehicule_id'],
                'montant_total' => $montantNet,
                'reduction'     => $reduction,
                'statut'        => 'livraison_en_cours',
            ]);

            // === 2) Lignes de commande ===
            $commandeLignes = [];
            foreach ($validated['lignes'] as $l) {
                $commandeLignes[] = new CommandeLigne([
                    'produit_id'         => $l['produit_id'],
                    'prix_vente'         => $l['prix_vente'],
                    'quantite_commandee' => $l['quantite'],
                    'quantite_restante'  => $l['quantite'],
                ]);
            }
            $commande->lignes()->saveMany($commandeLignes);

            // === 3) Générer la facture liée ===
            $total = 0.0;

            /** @var FactureLivraison $facture */
            $facture = FactureLivraison::create([
                'commande_id' => $commande->id,
                'numero'      => $this->generateFactureNumero(),
                'montant_du'  => 0,
                'total'       => 0,
                'statut'      => FactureLivraison::STATUT_IMPAYE,
            ]);

            $factureLignes = [];
            foreach ($commande->lignes as $cl) {
                $produit = Produit::find($cl->produit_id);
                $qte = (int) $cl->quantite_commandee;
                $prixUnitaire = (float) $cl->prix_vente;

                $montant = round($prixUnitaire * $qte, 2);
                $total += $montant;

                $factureLignes[] = new FactureLigne([
                    'produit_id'       => $cl->produit_id,
                    'quantite'         => $qte,
                    'prix_unitaire_ht' => $prixUnitaire, // plus de TVA => prix final
                    'montant_ht'       => $montant,
                    'montant_ttc'      => $montant, // identique
                ]);
            }

            // Appliquer réduction éventuelle
            if ($reduction > 0) {
                $total = max(0, round($total - $reduction, 2));
            }

            $facture->lignes()->saveMany($factureLignes);

            // Mettre à jour les totaux
            $facture->total = $total;
            $facture->montant_du = $total;
            $facture->save();

            DB::commit();

            // === 4) Recharger les relations pour la réponse ===
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
     * Génère un numéro de facture du type FA00000042
     */
    private function generateFactureNumero(): string
    {
        $last = FactureLivraison::latest('id')->first();
        $next = $last ? $last->id + 1 : 1;
        return 'FA' . str_pad((string) $next, 8, '0', STR_PAD_LEFT);
    }
}
