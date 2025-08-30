<?php

namespace App\Http\Controllers\Livraison;

use App\Http\Controllers\Controller;
use App\Models\Commande;
use App\Models\Livraison;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LivraisonValidationController extends Controller
{
    /**
     * Valide la livraison d'une commande.
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

            // Créer la livraison
           // Créer la livraison avec la quantité livrée
$livraison = Livraison::create([
    'commande_id' => $commande->id,
    'date_livraison' => $validated['date_livraison'],
    'quantite_livree' => $validated['quantite_livree'],  // Assurez-vous que cette donnée est insérée
]);

            // Mise à jour de la ligne de commande (quantité restante)
            $ligneCommande->decrement('quantite_restante', $validated['quantite_livree']);

            // Si tout est livré, mise à jour du statut de la commande
            $nouveauStatut = $ligneCommande->quantite_restante == 0 ? 'livré' : 'livraison_en_cours';
            $commande->update(['statut' => $nouveauStatut]);

            // Commit transaction
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Livraison validée avec succès.',
                'livraison' => $livraison,
                'commande_statut' => $nouveauStatut,
            ]);
        } catch (\Throwable $e) {
            // Rollback transaction en cas d'erreur
            DB::rollBack();

            return response()->json(['error' => 'Erreur serveur', 'message' => $e->getMessage()], 500);
        }
    }
}
