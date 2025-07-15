<?php

namespace App\Http\Controllers\Packing;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Packing;
use Illuminate\Support\Facades\DB;
use App\Traits\JsonResponseTrait;

class PackingUpdateController extends Controller
{
    use JsonResponseTrait;

    public function __invoke(Request $request, int $id)
    {
        $packing = Packing::find($id);

        if (!$packing) {
            return $this->responseJson(false, 'Packing introuvable.', null, 404);
        }

        $validated = $request->validate([
            'date' => 'sometimes|date',
            'heure_debut' => 'sometimes',
            'heure_fin' => 'nullable',
            'statut' => 'sometimes|in:brouillon,en_cours,valider',
            'lignes' => 'nullable|array',
            'lignes.*.produit_id' => 'required_with:lignes|exists:produits,id',
            'lignes.*.quantite_utilisee' => 'required_with:lignes|integer|min:1',
        ]);

        DB::beginTransaction();
        try {
            $packing->update($request->only(['date', 'heure_debut', 'heure_fin', 'statut']));

            if (isset($validated['lignes'])) {
                $packing->lignes()->delete(); // On écrase les lignes
                foreach ($validated['lignes'] as $ligne) {
                    $packing->lignes()->create($ligne);
                }
            }

            DB::commit();
            return $this->responseJson(true, 'Packing mis à jour.', $packing->load('lignes.produit'));
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->responseJson(false, 'Erreur lors de la mise à jour.', ['error' => $e->getMessage()], 500);
        }
    }
}
