<?php

namespace App\Http\Controllers\Vehicules;

use App\Http\Controllers\Controller;
use App\Models\Vehicule;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Throwable;

class VehiculeUpdateController extends Controller
{
    use JsonResponseTrait;

    // PUT/PATCH /vehicules/{vehicule}
    public function updateById(Request $request, $id)
    {
        try {
            $vehicule = Vehicule::findOrFail($id);

            $data = $request->validate([
                'type'                => [Rule::in(['camion','fourgonette','tricycle'])],
                'immatriculation'     => ['string','max:60', Rule::unique('vehicules','immatriculation')->ignore($vehicule->id)],
                 'nom'    => ['string','max:120'],
                'nom_proprietaire'    => ['string','max:120'],
                'prenom_proprietaire' => ['string','max:120'],
                'phone_proprietaire'  => ['string','max:30'],
                'nom_livreur'    => ['string','max:120'],
                'prenom_livreur' => ['string','max:120'],
                'phone_livreur'  => ['string','max:30'],
            ]);

            $vehicule->update($data);

            return $this->responseJson(true, 'Véhicule mis à jour.', $vehicule);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->responseJson(false, 'Échec de validation.', $e->errors(), 422);
        } catch (ModelNotFoundException $e) {
            return $this->responseJson(false, 'Véhicule introuvable.', null, 404);
        } catch (QueryException $e) {
            Log::error('Vehicule update failed', ['id' => $id, 'error' => $e->getMessage()]);
            return $this->responseJson(false, 'Erreur lors de la mise à jour du véhicule.', null, 500);
        } catch (\Throwable $e) {
            Log::error('Vehicule update unexpected', ['id' => $id, 'error' => $e->getMessage()]);
            return $this->responseJson(false, 'Erreur inattendue.', null, 500);
        }
    }
}
