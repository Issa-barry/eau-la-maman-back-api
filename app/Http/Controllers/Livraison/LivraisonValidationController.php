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
     * Valider une livraison (multi-produits)
     * Payload recommandé :
     * {
     *   "date_livraison": "2025-09-01",
     *   "lignes": [
     *     { "commande_ligne_id": 59, "quantite_livree": 1 },
     *     { "commande_ligne_id": 60, "quantite_livree": 2 }
     *   ]
     * }
     * (Compat: si pas de "lignes", on accepte "quantite_livree" et on l’applique à la 1ère ligne)
     */
    public function valider(Request $request, string $commandeNumero)
    {
        // --- Validation souple (multi-lignes prioritaire)
        if ($request->has('lignes')) {
            $validated = $request->validate([
                'date_livraison'         => ['required','date'],
                'lignes'                 => ['required','array','min:1'],
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

                // Crée l’entête livraison (quantité totale calculée après)
                $livraison = Livraison::create([
                    'commande_id'     => $commande->id,
                    'date_livraison'  => $validated['date_livraison'],
                    'quantite_livree' => 0, // provisoire
                ]);

                $factureTotal = 0;
                $totalLivree  = 0;
                $lignesCreees = [];

                // --- Cas multi-lignes
                if (!empty($validated['lignes'])) {
                    foreach ($validated['lignes'] as $l) {
                        /** @var CommandeLigne $cl */
                        $cl = CommandeLigne::lockForUpdate()->with('produit')->findOrFail($l['commande_ligne_id']);

                        $dejaLivre = (int) $cl->livraisonLignes()->sum('quantite');
                        $restant   = (int) $cl->quantite_commandee - $dejaLivre;

                        if ($l['quantite_livree'] > $restant) {
                            throw ValidationException::withMessages([
                                "lignes.{$cl->id}.quantite_livree" => ["La quantité dépasse le restant ($restant)."]
                            ]);
                        }

                        // Créer la ligne de livraison
                        $ll = LivraisonLigne::create([
                            'livraison_id'      => $livraison->id,
                            'commande_ligne_id' => $cl->id,
                            'produit_id'        => $cl->produit_id,
                            'quantite'          => (int) $l['quantite_livree'],
                            'montant_payer'     => (float) $cl->prix_vente * (int) $l['quantite_livree'],
                        ]);
                        $lignesCreees[] = $ll;

                        // MAJ restant ligne
                        $cl->decrement('quantite_restante', (int) $l['quantite_livree']);

                        $totalLivree  += (int) $l['quantite_livree'];
                        $factureTotal += (float) $cl->prix_vente * (int) $l['quantite_livree'];
                    }
                }
                // --- Compat : une seule quantité pour la 1ère ligne
                else {
                    /** @var CommandeLigne $cl */
                    $cl = CommandeLigne::lockForUpdate()->with('produit')
                        ->where('commande_id', $commande->id)->orderBy('id')->first();

                    if (!$cl) throw ValidationException::withMessages(['commande' => ['Aucune ligne de commande.']]);

                    $dejaLivre = (int) $cl->livraisonLignes()->sum('quantite');
                    $restant   = (int) $cl->quantite_commandee - $dejaLivre;

                    if ($validated['quantite_livree'] > $restant) {
                        throw ValidationException::withMessages([
                            "quantite_livree" => ["La quantité dépasse le restant ($restant)."]
                        ]);
                    }

                    $ll = LivraisonLigne::create([
                        'livraison_id'      => $livraison->id,
                        'commande_ligne_id' => $cl->id,
                        'produit_id'        => $cl->produit_id,
                        'quantite'          => (int) $validated['quantite_livree'],
                        'montant_payer'     => (float) $cl->prix_vente * (int) $validated['quantite_livree'],
                    ]);
                    $lignesCreees[] = $ll;

                    $cl->decrement('quantite_restante', (int) $validated['quantite_livree']);

                    $totalLivree  = (int) $validated['quantite_livree'];
                    $factureTotal = (float) $cl->prix_vente * (int) $validated['quantite_livree'];
                }

                // Met à jour l’entête livraison avec la somme réelle
                $livraison->update(['quantite_livree' => $totalLivree]);

                // Statut commande
                $commande->load('lignes'); // recharge les restants
                $toutesSoldees = $commande->lignes->every(fn($cl) => (int) $cl->quantite_restante <= 0);
                $commande->update(['statut' => $toutesSoldees ? 'livré' : 'livraison_en_cours']);

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
                    'message'         => 'Livraison validée, lignes enregistrées et facture (impayée) créée.',
                    'livraison'       => $livraison,
                    'facture'         => $facture,
                    'commande_statut' => $commande->statut,
                ], 201);
            });
        } catch (ValidationException $ve) {
            throw $ve;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'error'   => 'Erreur serveur',
                'message' => app()->isLocal() || config('app.debug') ? $e->getMessage() : 'Erreur interne'
            ], 500);
        }
    }
}
