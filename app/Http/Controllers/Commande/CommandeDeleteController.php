<?php

namespace App\Http\Controllers\Commande;

use App\Http\Controllers\Controller;
use App\Models\Commande;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Traits\JsonResponseTrait;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CommandeDeleteController extends Controller
{
    use JsonResponseTrait;

    /**
     * Supprimer une commande par ID.
     */
    public function deleteById(int $id)
    {
        DB::beginTransaction();

        try {
            $commande = Commande::findOrFail($id);

            // Supprimer les lignes associées
            $commande->lignes()->delete();

            // Supprimer la commande elle-même
            $commande->delete();

            DB::commit();

            return $this->responseJson(true, 'Commande supprimée avec succès.', null, 200);
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return $this->responseJson(false, 'Commande introuvable.', [], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->responseJson(false, 'Erreur lors de la suppression.', [
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Supprimer une commande par numéro.
     */
    public function deleteByNumero(string $numero)
    {
        DB::beginTransaction();

        try {
            $commande = Commande::where('numero', $numero)->firstOrFail();

            $commande->lignes()->delete();
            $commande->delete();

            DB::commit();

            return $this->responseJson(true, 'Commande supprimée avec succès.', null, 200);
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return $this->responseJson(false, 'Commande introuvable.', [], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->responseJson(false, 'Erreur lors de la suppression.', [
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
