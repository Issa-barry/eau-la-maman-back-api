<?php

namespace App\Http\Controllers\Packing;

use App\Http\Controllers\Controller;
use App\Models\Packing;
use App\Models\Produit;
use Illuminate\Support\Facades\DB;
use App\Traits\JsonResponseTrait;

class PackingValidationController extends Controller
{
    use JsonResponseTrait;

    // ID du produit fini à augmenter
    private const TARGET_PRODUIT_ID = 1;

    /**
     * Valider un packing :
     * - décrémente le stock du "rouleau" (packing.produit_id)
     * - incrémente le stock du produit fini (id=1)
     * - passe le statut du packing à "validé"
     */
    public function valider($id)
    {
        DB::beginTransaction();

        try {
            /** @var Packing $packing */
            $packing = Packing::with(['contact', 'produit'])->findOrFail($id);

            if ($packing->statut === 'validé') {
                return $this->responseJson(false, 'Ce packing est déjà validé.', null, 400);
            }

            $qty = (int) ($packing->quantite_packed ?? 0);
            if (empty($packing->produit_id) || $qty <= 0) {
                return $this->responseJson(false, 'Produit ou quantité invalides pour ce packing.', null, 422);
            }

            // On verrouille les 2 produits dans un ordre constant (évite les deadlocks)
            $ids = collect([$packing->produit_id, self::TARGET_PRODUIT_ID])->unique()->sort()->values();

            /** @var \Illuminate\Support\Collection<int,Produit> $locked */
            $locked = Produit::whereIn('id', $ids)->lockForUpdate()->get()->keyBy('id');

            // Récupère le rouleau (consommé) et le produit fini (augmenté)
            $rouleau = $locked->get($packing->produit_id);
            $target  = $locked->get(self::TARGET_PRODUIT_ID);

            if (!$rouleau) {
                return $this->responseJson(false, "Produit rouleau (id={$packing->produit_id}) introuvable.", null, 404);
            }
            if (!$target) {
                return $this->responseJson(false, "Produit cible (id=" . self::TARGET_PRODUIT_ID . ") introuvable.", null, 404);
            }

            // Vérifie le stock disponible sur le rouleau
            $stockRouleau = (int) ($rouleau->quantite_stock ?? 0);
            if ($stockRouleau < $qty) {
                DB::rollBack();
                return $this->responseJson(false, "Stock rouleau insuffisant ($stockRouleau < $qty).", null, 422);
            }

            // Mouvement de stock
            $rouleau->quantite_stock = $stockRouleau - $qty;               // ↓ consomme
            $target->quantite_stock  = (int) ($target->quantite_stock ?? 0) + $qty; // ↑ produit fini

            $rouleau->save();
            $target->save();

            // Statut du packing
            $packing->statut = 'validé';
            $packing->save();

            DB::commit();

            // Retourne avec les relations + stocks mis à jour
            $packing->load(['contact', 'produit']);
            $result = [
                'packing'        => $packing,
                'rouleau_stock'  => $rouleau->only(['id','nom','quantite_stock']),
                'target_stock'   => $target->only(['id','nom','quantite_stock']),
            ];

            return $this->responseJson(true, 'Packing validé: stock mis à jour (rouleau ↓, produit id=1 ↑).', $result);

        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->responseJson(false, 'Erreur lors de la validation du packing.', $e->getMessage(), 500);
        }
    }
}
