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
    public function __invoke(Request $request, $vehicule)
    {
        try {
            $row = Vehicule::findOrFail($vehicule);

            $data = $request->validate([
                'type'               => [Rule::in(['camion','fourgonette','tricycle'])],
                'marque'             => ['nullable','string','max:120'],
                'immatriculation'    => ['nullable','string','max:60', Rule::unique('vehicules','immatriculation')->ignore($row->id)],
                'owner_contact_id'   => [Rule::exists('contacts','id')->where('type','proprietaire')],
                'livreur_contact_id' => [Rule::exists('contacts','id')->where('type','livreur'), Rule::unique('vehicules','livreur_contact_id')->ignore($row->id)],
            ]);

            $row->update($data);
            $row->load(['owner','livreur']);

            return $this->responseJson(true, 'Véhicule mis à jour.', $row);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->responseJson(false, 'Échec de validation.', $e->errors(), 422);
        } catch (ModelNotFoundException $e) {
            return $this->responseJson(false, 'Véhicule introuvable.', null, 404);
        } catch (QueryException $e) {
            Log::error('Vehicule update failed', ['id' => $vehicule, 'error' => $e->getMessage()]);
            return $this->responseJson(false, 'Erreur lors de la mise à jour du véhicule.', null, 500);
        } catch (Throwable $e) {
            Log::error('Vehicule update unexpected', ['id' => $vehicule, 'error' => $e->getMessage()]);
            return $this->responseJson(false, 'Erreur inattendue.', null, 500);
        }
    }
}
