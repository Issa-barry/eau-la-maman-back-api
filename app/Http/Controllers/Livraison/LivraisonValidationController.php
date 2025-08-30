<?php 
namespace App\Http\Controllers\Livraison;

use App\Http\Controllers\Controller;
use App\Models\Commande;
use App\Models\Livraison;
use App\Models\FactureLivraison;
use App\Models\FactureLigne;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LivraisonValidationController extends Controller
{
    /**
     * Valide la livraison d'une commande et génère une facture.
     */
    public function valider(Request $request, string $commandeNumero)
    {
        try {
            // Validation des données de la requête
            $validated = $request->validate([
                'date_livraison' => 'required|date', // Date de la livraison
                'quantite_livree' => 'required|integer|min:1', // Quantité livrée
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => 'Données invalides', 'details' => $e->errors()], 422);
        }

        DB::beginTransaction();

        try {
            // Récupérer la commande par son numéro
            $commande = Commande::with('lignes') // Pas besoin de 'produit' ici
                ->where('numero', $commandeNumero)
                ->firstOrFail();

            // Vérifier que la commande a bien une ligne associée et la quantité restante
            $ligneCommande = $commande->lignes->first();
            
            if ($ligneCommande->quantite_restante <= 0) {
                return response()->json([ 
                    'error' => "Cette commande a déjà été entièrement livrée."
                ], 422);
            }

            // Vérifier que la quantité livrée ne dépasse pas la quantité restante
            if ($validated['quantite_livree'] > $ligneCommande->quantite_restante) {
                return response()->json([ 
                    'error' => "La quantité livrée dépasse la quantité restante de cette commande"
                ], 422);
            }

            // Créer la livraison avec la quantité livrée
            $livraison = Livraison::create([
                'commande_id' => $commande->id,
                'date_livraison' => $validated['date_livraison'],
                'quantite_livree' => $validated['quantite_livree'],
            ]);

            // Mise à jour de la ligne de commande (quantité restante)
            $ligneCommande->decrement('quantite_restante', $validated['quantite_livree']);

            // Retourner au stock la quantité non livrée
            $quantiteRetournee = $ligneCommande->quantite_restante; 
            if ($quantiteRetournee > 0) {
                // Réintégrer la quantité non livrée dans le stock
                $produit = $ligneCommande->produit;
                $produit->increment('quantite_stock', $quantiteRetournee);
            }

            // Si tout est livré ou retourné au stock, mise à jour du statut de la commande
            $nouveauStatut = 'livré';
            $commande->update(['statut' => $nouveauStatut]);

            // Générer une facture de livraison après validation de la livraison
            $montantTotal = $validated['quantite_livree'] * $ligneCommande->prix_vente;

            // Créer la facture de livraison
            $factureLivraison = FactureLivraison::create([
                'commande_id' => $commande->id,
                'numero' => 'FAC-' . now()->format('Ymd') . '-' . str_pad(FactureLivraison::max('id') + 1, 4, '0', STR_PAD_LEFT),
                'montant_du' => $montantTotal,
                'total' => $montantTotal,
                'statut' => FactureLivraison::STATUT_BROUILLON, // Facture dans un état brouillon
            ]);

            // Créer les lignes de facture pour chaque produit livré
            FactureLigne::create([
                'facture_id' => $factureLivraison->id,
                'produit_id' => $ligneCommande->produit_id,
                'quantite' => $validated['quantite_livree'],
                'prix_unitaire_ht' => $ligneCommande->prix_vente,
                'montant_ht' => $montantTotal,
                'montant_ttc' => $montantTotal, // pas de TVA ici
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Livraison validée avec succès, facture générée.',
                'livraison' => $livraison,
                'facture' => $factureLivraison,
                'commande_statut' => $nouveauStatut,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json(['error' => 'Erreur serveur', 'message' => $e->getMessage()], 500);
        }
    }
}
