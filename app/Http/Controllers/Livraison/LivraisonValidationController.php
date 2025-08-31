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
     * Valide la livraison d'une commande et crée la facture associée.
     */
    public function valider(Request $request, string $commandeNumero)
    {
        try {
            // Validation des données de la requête
            $validated = $request->validate([
                'date_livraison' => 'required|date',  // Date de la livraison
                'quantite_livree' => 'required|integer|min:1',  // Quantité livrée
            ]);

            // Récupérer la commande par son numéro
            $commande = Commande::where('numero', $commandeNumero)
                ->with('lignes')  // Charger les lignes associées
                ->first();

            // Vérification si la commande existe
            if (!$commande) {
                return response()->json(['error' => 'Commande non trouvée'], 404);
            }

            // Vérification du statut de la commande (seulement "livraison_en_cours" peut être validé)
            if ($commande->statut !== 'livraison_en_cours') {
                return response()->json(['error' => 'Seules les commandes avec le statut "livraison_en_cours" peuvent être validées pour la livraison.'], 422);
            }

            // Vérification de la quantité livrée
            $ligneCommande = $commande->lignes->first();
            if ($ligneCommande->quantite_restante <= 0) {
                return response()->json(['error' => 'Cette commande a déjà été entièrement livrée.'], 422);
            }

            // Vérifier que la quantité livrée ne dépasse pas la quantité restante
            if ($validated['quantite_livree'] > $ligneCommande->quantite_restante) {
                return response()->json(['error' => 'La quantité livrée dépasse la quantité restante de cette commande'], 422);
            }

            // Créer la livraison avec la quantité livrée
            $livraison = Livraison::create([
                'commande_id' => $commande->id,
                'date_livraison' => $validated['date_livraison'],
                'quantite_livree' => $validated['quantite_livree'],
            ]);

            // Mise à jour de la ligne de commande (quantité restante)
            $ligneCommande->decrement('quantite_restante', $validated['quantite_livree']);

            // Si tout est livré, mise à jour du statut de la commande
            $nouveauStatut = $ligneCommande->quantite_restante == 0 ? 'livré' : 'livraison_en_cours';
            $commande->update(['statut' => $nouveauStatut]);

            // Mise à jour du stock (si des produits sont retournés)
            if ($validated['quantite_livree'] < $ligneCommande->quantite_restante) {
                // Incrémenter le stock pour la quantité retournée
                $ligneCommande->produit->increment('quantite_stock', $validated['quantite_livree']);
            }

            // Créer la facture de livraison
            $factureLivraison = FactureLivraison::create([
                'commande_id' => $commande->id,
                'numero' => 'FAC-' . now()->format('Ymd') . '-' . str_pad(FactureLivraison::max('id') + 1, 4, '0', STR_PAD_LEFT),
                'montant_du' => $validated['quantite_livree'] * $ligneCommande->prix_vente,
                'total' => $validated['quantite_livree'] * $ligneCommande->prix_vente,
                'statut' => FactureLivraison::STATUT_BROUILLON, // Facture en statut brouillon
            ]);

            // Créer la ligne de facture pour ce produit livré
            FactureLigne::create([
                'facture_id' => $factureLivraison->id,
                'produit_id' => $ligneCommande->produit_id,
                'quantite' => $validated['quantite_livree'],
                'prix_unitaire_ht' => $ligneCommande->prix_vente,
                'montant_ht' => $validated['quantite_livree'] * $ligneCommande->prix_vente,
                'montant_ttc' => $validated['quantite_livree'] * $ligneCommande->prix_vente, // Pas de TVA ici
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Livraison validée et facture générée avec succès.',
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
