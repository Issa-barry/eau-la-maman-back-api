<?php

namespace App\Http\Controllers\Vehicules;

use App\Http\Controllers\Controller;
use App\Models\Vehicule;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class VehiculesIndexShowController extends Controller
{
    use JsonResponseTrait;

    // GET /vehicules/all?type=&owner_contact_id=&livreur_contact_id=&per_page=&page=
    public function index(Request $r)
    {
        try {
            $r->validate([
                'type'               => 'nullable|in:camion,fourgonette,tricycle',
                'per_page'           => 'nullable|integer|min:1|max:100',
                'page'               => 'nullable|integer|min:1',
            ]);

            //  Pas d’eager-load sur une relation non garantie côté BDD
            $q = Vehicule::query();

            if ($r->filled('type'))               { $q->where('type', $r->input('type')); }
  
            $perPage = (int) $r->input('per_page', 15);
            if ($perPage < 1 || $perPage > 100)   { $perPage = 15; }

            $rows = $q->latest()->paginate($perPage);

            return $this->responseJson(true, 'Liste des véhicules.', $rows);
        } catch (ValidationException $e) {
            return $this->responseJson(false, 'Échec de validation.', $e->errors(), 422);
        } catch (QueryException $e) {
            Log::error('Vehicules index query error', ['error' => $e->getMessage()]);
            return $this->responseJson(false, 'Erreur lors de la récupération des véhicules.', null, 500);
        } catch (Throwable $e) {
            Log::error('Vehicules index unexpected error', ['error' => $e->getMessage()]);
            return $this->responseJson(false, 'Erreur inattendue.', null, 500);
        }
    }

    // GET /vehicules/getById/{id}
    public function show($id)
    {
        try {
            $vehicule = Vehicule::findOrFail($id);
            return $this->responseJson(true, 'Détail du véhicule.', $vehicule);
        } catch (ModelNotFoundException $e) {
            return $this->responseJson(false, 'Véhicule introuvable.', null, 404);
        } catch (QueryException $e) {
            Log::error('Vehicule show query error', ['id' => $id, 'error' => $e->getMessage()]);
            return $this->responseJson(false, 'Erreur lors de la récupération du véhicule.', null, 500);
        } catch (Throwable $e) {
            Log::error('Vehicule show unexpected error', ['id' => $id, 'error' => $e->getMessage()]);
            return $this->responseJson(false, 'Erreur inattendue.', null, 500);
        }
    }
}
