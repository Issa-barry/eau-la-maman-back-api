<?php

namespace App\Http\Controllers\Encaissement;

use App\Http\Controllers\Controller;
use App\Models\Encaissement;
use App\Traits\JsonResponseTrait;

class EncaissementIndexController extends Controller
{
    use JsonResponseTrait;

    public function index()
    {
        try {
            $encaissements = Encaissement::with('facture')->latest()->get();
            return $this->responseJson(true, 'Liste des encaissements.', $encaissements);
        } catch (\Throwable $e) {
            return $this->responseJson(false, 'Erreur lors de la récupération.', [
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
