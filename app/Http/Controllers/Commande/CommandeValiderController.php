<?php

namespace App\Http\Controllers\Commande;

use App\Http\Controllers\Controller;
use App\Models\Commande;
use App\Models\Facture;
use App\Models\FactureLigne;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Traits\JsonResponseTrait;

class CommandeValiderController extends Controller
{
    use JsonResponseTrait;

    /**
     * Valide la commande, ne crée PAS de livraison,
     * mais génère automatiquement une facture (brouillon/en_attente_paiement).
     */
    public function valider($numero)
    {
        $commande = Commande::where('numero', $numero)
            ->with('lignes.produit')
            ->firstOrFail();

        if ($commande->statut !== 'brouillon') {
            return $this->responseJson(false, 'Seules les commandes en brouillon peuvent être validées.', null, 400);
        }

        // Vérifs basiques (quantité et stock)
        foreach ($commande->lignes as $ligne) {
            $produit  = $ligne->produit;
            $quantite = $ligne->quantite_commandee;

            if (!is_numeric($quantite) || $quantite <= 0) {
                return $this->responseJson(false, "Quantité invalide pour le produit : {$produit->nom}", [
                    'quantite' => $quantite
                ], 400);
            }

            if ($produit->quantite_stock < $quantite) {
                return $this->responseJson(false, "Stock insuffisant pour le produit : {$produit->nom}", null, 400);
            }
        }

        DB::beginTransaction();

        try {
            // 1) (option) décrémenter le stock dès validation.
            //    Si tu préfères décrémenter au moment de l'encaissement, commente ce bloc.
            foreach ($commande->lignes as $ligne) {
                $ligne->produit->decrement('quantite_stock', $ligne->quantite_commandee);
            }

            // 2) Mettre à jour le statut de la commande pour refléter la facturation
            $commande->update(['statut' => 'facturation_en_cours']);

            // 3) Créer la facture (par ex. statut "en_attente_paiement")
            $numeroFacture = $this->generateNumeroFacture();

            $facture = Facture::create([
                'commande_id'    => $commande->id,
                'client_id'      => $commande->contact_id,
                'numero'         => $numeroFacture,
                'date_facture'   => now(),
                'statut'         => 'en_attente_paiement', // ou 'brouillon' selon ton flux
                'total_ht'       => 0,
                'total_tva'      => 0,
                'total_ttc'      => 0,
            ]);

            // 4) Créer les lignes de facture à partir des lignes de commande
            $totalHT  = 0;
            $totalTVA = 0;
            $tauxTVA  = config('app.taux_tva', 0); // ex: 0.18 (18%). Mets 0 si non applicable.

            foreach ($commande->lignes as $ligne) {
                $qte   = (float) $ligne->quantite_commandee;
                $puHT  = (float) $ligne->prix_vente; // suppose prix_vente HT; adapte si TTC
                $montantHT  = $qte * $puHT;
                $montantTVA = $montantHT * $tauxTVA;
                $montantTTC = $montantHT + $montantTVA;

                FactureLigne::create([
                    'facture_id'   => $facture->id,
                    'produit_id'   => $ligne->produit_id,
                    'quantite'     => $qte,
                    'prix_unitaire_ht' => $puHT,
                    'montant_ht'   => $montantHT,
                    'montant_tva'  => $montantTVA,
                    'montant_ttc'  => $montantTTC,
                ]);

                $totalHT  += $montantHT;
                $totalTVA += $montantTVA;
            }

            // 5) Finaliser les totaux de la facture
            $facture->update([
                'total_ht'  => $totalHT,
                'total_tva' => $totalTVA,
                'total_ttc' => $totalHT + $totalTVA,
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

    /**
     * Génère un numéro de facture unique.
     * Adapte la logique si tu veux un format séquentiel.
     */
    private function generateNumeroFacture(): string
    {
        // Format: FAC-YYYYMMDD-xxxx (uuid court)
        return 'FAC-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6));
    }
}
