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

    /**
     * Créer un nouveau packing.
     */
    public function store(Request $request)
    {
        try {
            // Validation
            $validated = $request->validate([
                'user_id' => 'required|exists:users,id',
                'date' => 'required|date',
                'heure_debut' => 'required',
                'heure_fin' => 'nullable',
                'lignes' => 'required|array|min:1',
                'lignes.*.produit_id' => 'required|exists:produits,id',
                'lignes.*.quantite_packed' => 'required|integer|min:1',
            ]);

            DB::beginTransaction();

            // Générer une référence unique
            $reference = $this->generateReference();

            // Création du packing avec statut par défaut 'brouillon'
            $packing = Packing::create([
                'reference' => $reference,
                'user_id' => $validated['user_id'],
                'date' => $validated['date'],
                'heure_debut' => $validated['heure_debut'],
                'heure_fin' => $validated['heure_fin'],
                'statut' => 'brouillon',
            ]);

            // Création des lignes
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

    /**
     * Génère une référence unique pour un packing.
     */
    protected function generateReference(): string
    {
        $date = now()->format('Ymd');
        $count = Packing::whereDate('created_at', today())->count() + 1;
        return 'PK-' . $date . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
    }
}
