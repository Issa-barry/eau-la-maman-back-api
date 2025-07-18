<?php

namespace App\Http\Controllers\Encaissement;

use App\Http\Controllers\Controller;
use App\Models\Encaissement;
use App\Traits\JsonResponseTrait;

class EncaissementShowController extends Controller
{
    use JsonResponseTrait;

    public function show($id)
    {
        try {
            $encaissement = Encaissement::with('facture')->find($id);
            if (!$encaissement) {
                return $this->responseJson(false, 'Encaissement non trouvé.', null, 404);
            }

            return $this->responseJson(true, 'Détail de l\'encaissement.', $encaissement);
        } catch (\Throwable $e) {
            return $this->responseJson(false, 'Erreur serveur.', ['error' => $e->getMessage()], 500);
        }
    }
}
