<?php

namespace App\Http\Controllers\Livraison;

use App\Http\Controllers\Controller;
use App\Models\Commande;
use App\Models\Livraison;
use App\Models\FactureLivraison;
use App\Models\FactureLigne;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LivraisonValidationController extends Controller
{
    /**
     * Valide la livraison d'une commande et crée la facture associée (statut IMPAYÉ).
     */
    public function valider(Request $request, string $commandeNumero)
    {
        $validated = $request->validate([
            'date_livraison'  => ['required','date'],
            'quantite_livree' => ['required','integer','min:1'],
        ]);

        // Récupération commande + lignes + produit (pour le prix)
        $commande = Commande::where('numero', $commandeNumero)
            ->with(['lignes.produit', 'contact'])
            ->first();

        if (!$commande) {
            return response()->json(['error' => 'Commande non trouvée'], 404);
        }

        if ($commande->statut !== 'livraison_en_cours') {
            return response()->json([
                'error' => 'Seules les commandes avec le statut "livraison_en_cours" peuvent être validées pour la livraison.'
            ], 422);
        }

        $ligneCommande = $commande->lignes->first(); // si 1 ligne par commande
        if (!$ligneCommande) {
            return response()->json(['error' => 'Aucune ligne de commande.'], 422);
        }

        if ($ligneCommande->quantite_restante <= 0) {
            return response()->json(['error' => 'Cette commande a déjà été entièrement livrée.'], 422);
        }

        if ($validated['quantite_livree'] > $ligneCommande->quantite_restante) {
            return response()->json(['error' => 'La quantité livrée dépasse la quantité restante de cette commande'], 422);
        }

        try {
            DB::beginTransaction();

            // 1) Créer la livraison
            $livraison = Livraison::create([
                'commande_id'      => $commande->id,
                'date_livraison'   => $validated['date_livraison'],
                'quantite_livree'  => $validated['quantite_livree'],
            ]);

            // 2) Mettre à jour la commande (quantité restante + statut)
            $ligneCommande->decrement('quantite_restante', $validated['quantite_livree']);

            $nouveauStatut = $ligneCommande->quantite_restante == 0 ? 'livré' : 'livraison_en_cours';
            $commande->update(['statut' => $nouveauStatut]);

            // 3) Préparer les montants
            $qte   = (int) $validated['quantite_livree'];
            $pu    = (float) $ligneCommande->prix_vente;  // TTC dans ton modèle actuel
            $total = $qte * $pu;

            // 4) Créer la facture (statut IMPAYÉ directement)
            $factureLivraison = FactureLivraison::create([
                'commande_id' => $commande->id,
                'numero'      => 'FAC-'.now()->format('Ymd').'-'.str_pad((int) (FactureLivraison::max('id') + 1), 4, '0', STR_PAD_LEFT),
                'total'       => $total,
                'montant_du'  => $total, // aucun encaissement au moment de la livraison
                'statut'      => FactureLivraison::STATUT_IMPAYE, // ← ici
            ]);

            // 5) Créer la ligne de facture
            FactureLigne::create([
                'facture_id'       => $factureLivraison->id,
                'produit_id'       => $ligneCommande->produit_id,
                'quantite'         => $qte,
                'prix_unitaire_ht' => $pu,                 // si PU HT/TTC à affiner selon ton modèle
                'montant_ht'       => $total,              // idem
                'montant_ttc'      => $total,
            ]);

            DB::commit();

            // Recharger pour le front
            $factureLivraison->load(['lignes.produit', 'commande.contact']);

            return response()->json([
                'success'         => true,
                'message'         => 'Livraison validée et facture (impayée) générée avec succès.',
                'livraison'       => $livraison,
                'facture'         => $factureLivraison,
                'commande_statut' => $nouveauStatut,
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'error'   => 'Erreur serveur',
                'message' => app()->isLocal() || config('app.debug') ? $e->getMessage() : 'Erreur interne'
            ], 500);
        }
    }
}
