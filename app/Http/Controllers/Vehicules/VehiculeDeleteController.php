<?php

namespace App\Http\Controllers\Vehicules;

use App\Http\Controllers\Controller;
use App\Models\Vehicule;
use App\Traits\JsonResponseTrait;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Throwable;

class VehiculeDeleteController extends Controller
{
    use JsonResponseTrait;

    // DELETE /vehicules/{vehicule}
    public function __invoke($vehicule)
    {
        try {
            $row = Vehicule::findOrFail($vehicule);
            $row->delete();
            return $this->responseJson(true, 'Véhicule supprimé.');
        } catch (ModelNotFoundException $e) {
            return $this->responseJson(false, 'Véhicule introuvable.', null, 404);
        } catch (QueryException $e) {
            Log::error('Vehicule delete failed', ['id' => $vehicule, 'error' => $e->getMessage()]);
            return $this->responseJson(false, 'Erreur lors de la suppression du véhicule.', null, 500);
        } catch (Throwable $e) {
            Log::error('Vehicule delete unexpected', ['id' => $vehicule, 'error' => $e->getMessage()]);
            return $this->responseJson(false, 'Erreur inattendue.', null, 500);
        }
    }
}
