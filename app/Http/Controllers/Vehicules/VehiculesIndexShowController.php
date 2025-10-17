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

    /**
     * GET /vehicules/all
     * Params:
     *  - immatriculation (string, optional) : recherche partielle (LIKE)
     *  - type               (enum: camion,fourgonette,tricycle)
     *  - owner_contact_id   (int)     // si tu l’utilises
     *  - livreur_contact_id (int)     // si tu l’utilises
     *  - per_page           (1..100)
     *  - page               (>=1)
     */
   // app/Http/Controllers/Vehicules/VehiculesIndexShowController.php

public function index(Request $r)
{
    try {
        $r->validate([
            'immatriculation'    => 'nullable|string|max:100',
            'type'               => 'nullable|in:camion,fourgonette,tricycle',
            'per_page'           => 'nullable|integer|min:1|max:100',
            'page'               => 'nullable|integer|min:1',
        ]);

        $q = Vehicule::query(); // ❌ pas de ->with()

        if ($r->filled('type')) {
            $q->where('type', $r->input('type'));
        }

        if ($r->filled('immatriculation')) {
            $term = $r->query('immatriculation');
            $term = str_replace(['\\','%','_'], ['\\\\','\%','\_'], $term);
            $q->where('immatriculation', 'like', "%{$term}%");
        }

        $perPage = max(1, min(100, (int) $r->input('per_page', 15)));
        $rows = $q->latest()->paginate($perPage);

        return $this->responseJson(true, 'Liste des véhicules.', $rows);
    } catch (\Illuminate\Validation\ValidationException $e) {
        return $this->responseJson(false, 'Échec de validation.', $e->errors(), 422);
    } catch (\Throwable $e) {
        \Log::error('Vehicules index error', ['ex' => $e]);
        return $this->responseJson(false, 'Erreur inattendue.', null, 500);
    }
}

public function show($id)
{
    try {
        $vehicule = Vehicule::findOrFail($id); // ❌ pas de ->with()
        return $this->responseJson(true, 'Détail du véhicule.', $vehicule);
    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        return $this->responseJson(false, 'Véhicule introuvable.', null, 404);
    } catch (\Throwable $e) {
        \Log::error('Vehicule show error', ['id' => $id, 'ex' => $e]);
        return $this->responseJson(false, 'Erreur inattendue.', null, 500);
    }
}

}
