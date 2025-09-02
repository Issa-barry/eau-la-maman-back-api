<?php

namespace App\Http\Controllers\Livraison;

use App\Http\Controllers\Controller;
use App\Models\Commande;
use App\Models\CommandeLigne;
use App\Models\Livraison;
use App\Models\LivraisonLigne;
use App\Models\FactureLivraison;
use App\Models\FactureLigne;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LivraisonValidationController extends Controller
{
    /**
     * Valider une livraison (multi-produits) et clôturer la commande.
     * Règle métier : dès qu'on passe ici, la commande devient "livré"
     * (sans modifier les quantités restantes des lignes).
     *
     * Payload:
     * {
     *   "date_livraison": "2025-09-01",
     *   "lignes": [
     *     { "commande_ligne_id": 59, "quantite_livree": 1 },
     *     { "commande_ligne_id": 60, "quantite_livree": 2 }
     *   ]
     * }
     * Compat : sans "lignes", on accepte "quantite_livree" pour la 1ère ligne.
     */
    public function valider(Request $request, string $commandeNumero)
    {
        // Validation souple (multi-lignes prioritaire)
        if ($request->has('lignes')) {
            $validated = $request->validate([
                'date_livraison'             => ['required','date'],
                'lignes'                     => ['required','array','min:1'],
                'lignes.*.commande_ligne_id' => ['required','exists:commande_lignes,id'],
                'lignes.*.quantite_livree'   => ['required','integer','min:1'],
            ]);
        } else {
            $validated = $request->validate([
                'date_livraison'  => ['required','date'],
                'quantite_livree' => ['required','integer','min:1'],
            ]);
        }

        $commande = Commande::where('numero', $commandeNumero)
            ->with(['lignes.produit', 'contact'])
            ->first();

        if (!$commande) {
            return response()->json(['error' => 'Commande non trouvée'], 404);
        }

        if (!in_array($commande->statut, ['brouillon','livraison_en_cours'])) {
            return response()->json([
                'error' => 'Statut incompatible. Il faut "brouillon" ou "livraison_en_cours".'
            ], 422);
        }

        try {
            return DB::transaction(function () use ($commande, $validated) {

                // Entête de livraison (quantité totale calculée après)
                $livraison = Livraison::create([
                    'commande_id'     => $commande->id,
                    'date_livraison'  => $validated['date_livraison'],
                    'quantite_livree' => 0, // provisoire
                ]);

                $factureTotal = 0.0;
                $totalLivree  = 0;
                $lignesCreees = [];

                // MULTI-LIGNES
                if (!empty($validated['lignes'])) {
                    foreach ($validated['lignes'] as $l) {
                        /** @var CommandeLigne $cl */
                        $cl = CommandeLigne::lockForUpdate()
                            ->with(['produit', 'livraisonLignes'])
                            ->findOrFail($l['commande_ligne_id']);

                        // sécurité : la ligne doit appartenir à la commande
                        if ((int) $cl->commande_id !== (int) $commande->id) {
                            throw ValidationException::withMessages([
                                "lignes.{$cl->id}.commande_ligne_id" => ["La ligne n'appartient pas à la commande {$commande->numero}."]
                            ]);
                        }

                        // contrôle optionnel : ne pas livrer plus que commandé
                        $dejaLivre = (int) $cl->livraisonLignes->sum('quantite');
                        $restant   = max(0, (int) $cl->quantite_commandee - $dejaLivre);
                        if ((int) $l['quantite_livree'] > $restant) {
                            throw ValidationException::withMessages([
                                "lignes.{$cl->id}.quantite_livree" => ["La quantité dépasse le restant ($restant)."]
                            ]);
                        }

                        $qty     = (int) $l['quantite_livree'];
                        $montant = (float) $cl->prix_vente * $qty;

                        $ll = LivraisonLigne::create([
                            'livraison_id'      => $livraison->id,
                            'commande_ligne_id' => $cl->id,
                            'produit_id'        => $cl->produit_id,
                            'quantite'          => $qty,
                            'montant_payer'     => $montant,
                        ]);
                        $lignesCreees[] = $ll;

                        // ⚠️ On NE TOUCHE PAS à quantite_restante ici
                        $totalLivree  += $qty;
                        $factureTotal += $montant;
                    }
                }
                // COMPAT : quantité unique sur la 1ère ligne
                else {
                    /** @var CommandeLigne $cl */
                    $cl = CommandeLigne::lockForUpdate()
                        ->with(['produit', 'livraisonLignes'])
                        ->where('commande_id', $commande->id)
                        ->orderBy('id')
                        ->first();

                    if (!$cl) {
                        throw ValidationException::withMessages(['commande' => ['Aucune ligne de commande.']]);
                    }

                    $dejaLivre = (int) $cl->livraisonLignes->sum('quantite');
                    $restant   = max(0, (int) $cl->quantite_commandee - $dejaLivre);
                    if ((int) $validated['quantite_livree'] > $restant) {
                        throw ValidationException::withMessages([
                            "quantite_livree" => ["La quantité dépasse le restant ($restant)."]
                        ]);
                    }

                    $qty     = (int) $validated['quantite_livree'];
                    $montant = (float) $cl->prix_vente * $qty;

                    $ll = LivraisonLigne::create([
                        'livraison_id'      => $livraison->id,
                        'commande_ligne_id' => $cl->id,
                        'produit_id'        => $cl->produit_id,
                        'quantite'          => $qty,
                        'montant_payer'     => $montant,
                    ]);
                    $lignesCreees[] = $ll;

                    // ⚠️ On NE TOUCHE PAS à quantite_restante ici
                    $totalLivree  = $qty;
                    $factureTotal = $montant;
                }

                // Met à jour l’entête livraison avec la somme réelle
                $livraison->update(['quantite_livree' => $totalLivree]);

                // === Règle : on clôture systématiquement la commande ===
                $commande->update(['statut' => 'livré']);

                // FACTURE (impayée) avec 1 ligne par produit livré
                $facture = FactureLivraison::create([
                    'commande_id' => $commande->id,
                    'numero'      => 'FAC-'.now()->format('Ymd').'-'.str_pad((int) (FactureLivraison::max('id') + 1), 4, '0', STR_PAD_LEFT),
                    'total'       => $factureTotal,
                    'montant_du'  => $factureTotal,
                    'statut'      => FactureLivraison::STATUT_IMPAYE,
                ]);

                foreach ($lignesCreees as $ll) {
                    /** @var CommandeLigne $cl */
                    $cl = CommandeLigne::with('produit')->find($ll->commande_ligne_id);
                    FactureLigne::create([
                        'facture_id'       => $facture->id,
                        'produit_id'       => $cl->produit_id,
                        'quantite'         => (int) $ll->quantite,
                        'prix_unitaire_ht' => (float) $cl->prix_vente,
                        'montant_ht'       => (float) $cl->prix_vente * (int) $ll->quantite,
                        'montant_ttc'      => (float) $cl->prix_vente * (int) $ll->quantite,
                    ]);
                }

                // Réponse
                $facture->load(['lignes.produit', 'commande.contact']);
                $livraison->load('lignes.produit');

                return response()->json([
                    'success'         => true,
                    'message'         => 'Livraison validée, commande clôturée (livré) et facture (impayée) créée.',
                    'livraison'       => $livraison,
                    'facture'         => $facture,
                    'commande_statut' => $commande->statut, // toujours "livré"
                ], 201);
            });
        } catch (ValidationException $ve) {
            throw $ve;
        } catch (\Throwable $e) {
            return response()->json([
                'error'   => 'Erreur serveur',
                'message' => app()->isLocal() || config('app.debug') ? $e->getMessage() : 'Erreur interne'
            ], 500);
        }
    }
}
