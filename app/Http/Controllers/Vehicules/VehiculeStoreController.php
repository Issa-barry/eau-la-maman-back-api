<?php

namespace App\Http\Controllers\Vehicules;

use App\Http\Controllers\Controller;
use App\Models\Vehicule;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

class VehiculeStoreController extends Controller
{
    use JsonResponseTrait;

   public function __invoke(Request $request)
    {
        try {
            $data = $request->validate([
                'type'                => ['required', Rule::in(['camion','fourgonette','tricycle'])],
                'nom'                 => ['nullable','string','max:120'],
                'immatriculation'     => ['required','string','max:60','unique:vehicules,immatriculation'],

                // Propriétaire
                'nom_proprietaire'    => ['required','string','max:120'],
                'prenom_proprietaire' => ['required','string','max:120'],
                'phone_proprietaire'  => ['required','string','max:30'],

                // Livreur
                'nom_livreur'         => ['required','string','max:120'],
                'prenom_livreur'      => ['required','string','max:120'],
                'phone_livreur'       => ['required','string','max:30'],

                // ✅ Statut (optionnel, avec valeurs autorisées)
                'statut'              => ['nullable', Rule::in(['active','attente','bloque','archive'])],
            ]);

            // ✅ si non fourni, on force "active"
            $data['statut'] = $data['statut'] ?? 'active';

            $vehicule = Vehicule::create($data);

            // ✅ récupère les valeurs par défaut éventuelles de la BDD
            $vehicule->refresh();

            return $this->responseJson(true, 'Véhicule créé.', $vehicule, 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->responseJson(false, 'Échec de validation.', $e->errors(), 422);
        } catch (\Illuminate\Database\QueryException $e) {
            \Log::error('Vehicule create failed', ['error' => $e->getMessage()]);
            return $this->responseJson(false, 'Erreur lors de la création du véhicule.', null, 500);
        } catch (\Throwable $e) {
            \Log::error('Vehicule create unexpected', ['error' => $e->getMessage()]);
            return $this->responseJson(false, 'Erreur inattendue.', null, 500);
        }
    }
}
