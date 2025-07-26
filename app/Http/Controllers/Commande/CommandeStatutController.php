<?php

namespace App\Http\Controllers\Commande;

use App\Http\Controllers\Controller;
use App\Models\Commande;
use App\Models\Livraison;
use App\Models\LivraisonLigne;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommandeStatutController extends Controller
{

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
