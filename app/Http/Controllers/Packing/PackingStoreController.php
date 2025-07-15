<?php

namespace App\Http\Controllers\Packing;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Packing;
use App\Traits\JsonResponseTrait;
use Illuminate\Validation\ValidationException;

class PackingStoreController extends Controller
{
    use JsonResponseTrait;

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'user_id' => 'required|exists:users,id', // Ajouté ici
                'date' => 'required|date',
                'heure_debut' => 'required',
                'heure_fin' => 'nullable',
                'statut' => 'required|in:brouillon,en_cours,valider',
                'lignes' => 'required|array|min:1',
                'lignes.*.produit_id' => 'required|exists:produits,id',
                'lignes.*.quantite_utilisee' => 'required|integer|min:1',
            ]);

            DB::beginTransaction();

            $packing = Packing::create([
                'user_id' => $validated['user_id'], // Utilisé ici
                'date' => $validated['date'],
                'heure_debut' => $validated['heure_debut'],
                'heure_fin' => $validated['heure_fin'],
                'statut' => $validated['statut'],
            ]);

            foreach ($validated['lignes'] as $ligne) {
                $packing->lignes()->create($ligne);
            }

            DB::commit();

return $this->responseJson(true, 'Packing créé avec succès.', $packing->load(['user', 'lignes.produit']));

        } catch (ValidationException $e) {
            return $this->responseJson(false, 'Erreur de validation.', [
                'errors' => $e->errors()
            ], 422);
        } catch (\Throwable $e) {
            DB::rollBack();

            return $this->responseJson(false, 'Erreur serveur lors de la création du packing.', [
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
