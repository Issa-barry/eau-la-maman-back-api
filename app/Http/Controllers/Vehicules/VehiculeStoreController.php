<?php

namespace App\Http\Controllers\Vehicules;

use App\Http\Controllers\Controller;
use App\Models\Vehicule;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Throwable;

class VehiculeStoreController extends Controller
{
    use JsonResponseTrait;

    // POST /vehicules
    public function __invoke(Request $request)
    {
        try {
            $data = $request->validate([
                'type'                => ['required', Rule::in(['camion','fourgonette','tricycle'])],
                'immatriculation'     => ['required','string','max:60','unique:vehicules,immatriculation'],
                'nom_proprietaire'    => ['required','string','max:120'],
                'prenom_proprietaire' => ['required','string','max:120'],
                'phone_proprietaire'  => ['required','string','max:30'],
            ]);

            $vehicule = Vehicule::create($data);

            return $this->responseJson(true, 'Véhicule créé.', $vehicule, 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->responseJson(false, 'Échec de validation.', $e->errors(), 422);
        } catch (QueryException $e) {
            Log::error('Vehicule create failed', ['error' => $e->getMessage()]);
            return $this->responseJson(false, 'Erreur lors de la création du véhicule.', null, 500);
        } catch (\Throwable $e) {
            Log::error('Vehicule create unexpected', ['error' => $e->getMessage()]);
            return $this->responseJson(false, 'Erreur inattendue.', null, 500);
        }
    }
}
