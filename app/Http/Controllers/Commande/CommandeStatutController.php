<?php

namespace App\Http\Controllers\Commande;

use App\Http\Controllers\Controller;
use App\Models\Commande;
use Illuminate\Http\Request;

class CommandeStatutController extends Controller
{
    public function valider($numero)
    {
        $commande = Commande::where('numero', $numero)
            ->with('lignes.produit')
            ->firstOrFail();

        if ($commande->statut !== 'brouillon') {
            return response()->json([
                'success' => false,
                'message' => 'Seules les commandes en brouillon peuvent être validées.'
            ], 400);
        }

        // Vérification du stock
        foreach ($commande->lignes as $ligne) {
            $produit = $ligne->produit;

            if ($produit->quantite_stock < $ligne->quantite) {
                return response()->json([
                    'success' => false,
                    'message' => "Stock insuffisant pour le produit : {$produit->nom}."
                ], 400);
            }
        }

        // Décrément du stock
        foreach ($commande->lignes as $ligne) {
            $produit = $ligne->produit;
            $produit->decrement('quantite_stock', $ligne->quantite);
        }

        // Mise à jour du statut
        $commande->update(['statut' => 'livraison_en_cours']);

        return response()->json([
            'success' => true,
            'message' => 'Commande validée et stock mis à jour.',
            'commande' => $commande->fresh('lignes.produit')
        ]);
    }

    public function changerStatut(Request $request, $numero)
    {
        $request->validate([
            'statut' => 'required|in:brouillon,annulé,livraison_en_cours,livré,a_facturer,facturation_en_cours,payé'
        ]);

        $commande = Commande::where('numero', $numero)->firstOrFail();

        $statutActuel = $commande->statut;
        $nouveauStatut = $request->statut;

        // Vérifie si on tente de passer à un statut restreint sans avoir validé
        $statutsRestreints = [
            'livraison_en_cours',
            'livré',
            'a_facturer',
            'facturation_en_cours',
            'payé'
        ];

        if (
            in_array($nouveauStatut, $statutsRestreints) &&
            !in_array($statutActuel, $statutsRestreints)
        ) {
            return response()->json([
                'success' => false,
                'message' => "Impossible de passer au statut \"$nouveauStatut\" tant que la commande n'est pas validée."
            ], 400);
        }

        $commande->update(['statut' => $nouveauStatut]);

        return response()->json([
            'success' => true,
            'message' => "Statut mis à jour en \"$nouveauStatut\".",
            'commande' => $commande
        ]);
    }
}
