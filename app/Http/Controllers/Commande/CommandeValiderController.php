<?php

namespace App\Http\Controllers\Commande;

use App\Http\Controllers\Controller;
use App\Models\Commande;
use App\Models\FactureLivraison;
use App\Models\FactureLigne;
use Illuminate\Support\Facades\DB;
use App\Traits\JsonResponseTrait;

class CommandeValiderController extends Controller
{
    use JsonResponseTrait;

    /**
     * Valide la commande (contrôles + décrément stock),
     * bascule la commande en "livraison_en_cours",
     * puis génère la facture (statut brouillon) sur les quantités réellement chargées si dispo.
     */
    public function valider($numero)
    {
        $commande = Commande::where('numero', $numero)
            ->with('lignes.produit')
            ->firstOrFail();

        if ($commande->statut !== 'brouillon') {
            return $this->responseJson(false, 'Seules les commandes en brouillon peuvent être validées.', null, 400);
        }

        // 1) Contrôles préalables quantités/stock
        foreach ($commande->lignes as $ligne) {
            // Priorité à quantite_chargee si tu l'utilises, sinon quantite_commandee
            $qte = (float) ($ligne->quantite_chargee ?? $ligne->quantite_commandee);
            $produit = $ligne->produit;

            if (!is_numeric($qte) || $qte <= 0) {
                return $this->responseJson(false, "Quantité invalide pour le produit : {$produit->nom}", [
                    'quantite' => $qte
                ], 400);
            }
            if ($produit->quantite_stock < $qte) {
                return $this->responseJson(false, "Stock insuffisant pour le produit : {$produit->nom}", null, 400);
            }
        }

        // 2) Empêcher la double facturation
        if (FactureLivraison::where('commande_id', $commande->id)->exists()) {
            return $this->responseJson(false, 'Une facture existe déjà pour cette commande.', null, 409);
        }

        DB::beginTransaction();

        try {
            // 3) Décrément stock sur la quantité réellement chargée si présente
            foreach ($commande->lignes as $ligne) {
                $qte = (float) ($ligne->quantite_chargee ?? $ligne->quantite_commandee);
                if ($qte > 0) {
                    $ligne->produit->decrement('quantite_stock', $qte);
                }
            }

            // 4) Mettre le statut commande = livraison_en_cours (ENUM existant)
            $commande->update(['statut' => 'livraison_en_cours']);

            // 5) Générer le numéro de facture
            $date = now()->format('Ymd');
            $last = (int) (FactureLivraison::max('id') ?? 0) + 1;
            $numeroFacture = 'FAC-' . $date . '-' . str_pad($last, 4, '0', STR_PAD_LEFT);

            // 6) Créer la facture (statut brouillon)
            $facture = FactureLivraison::create([
                'commande_id' => $commande->id,
                'client_id'   => $commande->contact_id,
                'numero'      => $numeroFacture,
                'montant_du'  => 0,
                'total'       => 0,
                'statut'      => FactureLivraison::STATUT_BROUILLON,
            ]);

            // 7) Lignes de facture
            $total = 0;
            foreach ($commande->lignes as $ligne) {
                $qte  = (float) ($ligne->quantite_chargee ?? $ligne->quantite_commandee);
                $puHT = (float) $ligne->prix_vente;
                $mont = $qte * $puHT;

                FactureLigne::create([
                    'facture_id'       => $facture->id,
                    'produit_id'       => $ligne->produit_id,
                    'quantite'         => $qte,
                    'prix_unitaire_ht' => $puHT,
                    'montant_ht'       => $mont,
                    'montant_ttc'      => $mont, // pas de TVA ici
                ]);

                $total += $mont;
            }

            // 8) Refuser une facture à 0 (rien chargé)
            if ($total <= 0) {
                DB::rollBack();
                return $this->responseJson(false, "Impossible de générer une facture : total égal à 0.", null, 400);
            }

            // 9) Mettre à jour totaux facture
            $facture->update([
                'montant_du' => $total,
                'total'      => $total,
            ]);

            DB::commit();

            return $this->responseJson(true, 'Commande validée et facture générée.', [
                'commande' => $commande->fresh('lignes.produit'),
                'facture'  => $facture->load('lignes.produit'),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->responseJson(false, 'Erreur lors de la validation de la commande.', [
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
