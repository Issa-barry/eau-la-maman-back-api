<?php

namespace App\Http\Controllers\Livraison;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Models\Livraison;
use App\Traits\JsonResponseTrait;

class LivraisonDeleteController extends Controller
{
    use JsonResponseTrait;

    /**
     * Supprimer une livraison.
     */
    public function __invoke(int $id)
    {
        try {
            $livraison = Livraison::find($id);

            if (!$livraison) {
                return $this->responseJson(false, 'Livraison introuvable.', null, 404);
            }

            DB::beginTransaction();
            $livraison->delete();
            DB::commit();

            return $this->responseJson(true, 'Livraison supprimée avec succès.');

        } catch (\Throwable $e) {
            DB::rollBack();

            return $this->responseJson(false, 'Erreur serveur lors de la suppression de la livraison.', [
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
