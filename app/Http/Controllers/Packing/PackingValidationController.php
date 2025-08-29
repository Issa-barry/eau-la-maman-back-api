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

    /**
     * Valider un packing, mettre à jour le stock et changer le statut.
     */
    public function valider($id)
    {
        DB::beginTransaction();

        try {
            $packing = Packing::with('lignes')->findOrFail($id);

            if ($packing->statut === 'validé') {
                return $this->responseJson(false, 'Ce packing est déjà validé.', [], 400);
            }

            foreach ($packing->lignes as $ligne) {
                $produit = Produit::findOrFail($ligne->produit_id);
                $produit->quantite_stock += $ligne->quantite_packed;
                $produit->save();
            }

            $packing->update(['statut' => 'validé']);

            DB::commit();

            return $this->responseJson(true, 'Packing validé avec succès.', $packing->load(['user', 'lignes.produit']));

        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->responseJson(false, 'Erreur lors de la validation du packing.', [
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
