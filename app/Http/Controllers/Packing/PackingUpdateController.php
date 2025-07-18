<?php

namespace App\Http\Controllers\Packing;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Packing;
use App\Traits\JsonResponseTrait;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;

class PackingUpdateController extends Controller
{
    use JsonResponseTrait;

    public function update(Request $request, $id)
    {
        try {
            $packing = Packing::find($id);

            if (!$packing) {
                return $this->responseJson(false, 'Packing introuvable.', null, 404);
            }

            $validated = $request->validate([
                'user_id' => 'required|exists:users,id',
                'date' => 'required|date',
                'heure_debut' => 'required',
                'heure_fin' => 'nullable',
                'statut' => 'required|in:brouillon,en_cours,validé,annulé',
                'lignes' => 'required|array|min:1',
                'lignes.*.produit_id' => 'required|exists:produits,id',
                'lignes.*.quantite_packed' => 'required|integer|min:1',
            ]);

            DB::beginTransaction();

            $packing->update([
                'user_id' => $validated['user_id'],
                'date' => $validated['date'],
                'heure_debut' => $validated['heure_debut'],
                'heure_fin' => $validated['heure_fin'],
                'statut' => $validated['statut'],
            ]);

            $packing->lignes()->delete();

            foreach ($validated['lignes'] as $ligne) {
                $packing->lignes()->create($ligne);
            }

            DB::commit();

            return $this->responseJson(true, 'Packing mis à jour avec succès.', $packing->load(['user', 'lignes.produit']));

        } catch (ValidationException $e) {
            return $this->responseJson(false, 'Erreur de validation.', [
                'errors' => $e->errors()
            ], 422);

        } catch (QueryException $e) {
            DB::rollBack();

            return $this->responseJson(false, 'Erreur SQL lors de la mise à jour.', [
                'sql_error' => $e->getMessage(),
                'code' => $e->getCode()
            ], 500);

        } catch (\Throwable $e) {
            DB::rollBack();

            return $this->responseJson(false, 'Erreur serveur inattendue.', [
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
