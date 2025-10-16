<?php

namespace App\Http\Controllers\Vehicules;

use App\Http\Controllers\Controller;
use App\Models\Vehicule;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class VehiculeStatutController extends Controller
{
    use JsonResponseTrait;

    /**
     * Met à jour le statut d'un véhicule (ex: active, attente, bloque, archive)
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateStatut(Request $request, $id)
    {
        try {
            $vehicule = Vehicule::find($id);

            if (!$vehicule) {
                return $this->responseJson(false, 'Véhicule non trouvé.', null, 404);
            }

            $validated = $request->validate([
                'statut' => 'required|in:active,attente,bloque,archive',
            ]);

            $vehicule->statut = $validated['statut'];
            $vehicule->save();

            return $this->responseJson(true, 'Statut du véhicule mis à jour avec succès.', $vehicule);

        } catch (ValidationException $e) {
            return $this->responseJson(false, 'Erreur de validation.', $e->errors(), 422);

        } catch (\Exception $e) {
            return $this->responseJson(false, 'Une erreur est survenue.', $e->getMessage(), 500);
        }
    }
}
