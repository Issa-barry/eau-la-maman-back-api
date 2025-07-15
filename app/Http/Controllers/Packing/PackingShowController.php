<?php

namespace App\Http\Controllers\Packing;

use App\Http\Controllers\Controller;
use App\Models\Packing;
use App\Traits\JsonResponseTrait;

class PackingShowController extends Controller
{
    use JsonResponseTrait; 

    public function index()
    {
        $packings = Packing::with(['user', 'lignes.produit'])->latest()->get();
        return $this->responseJson(true, 'Liste des packings.', $packings);
    }


     public function show(int $id)
    {
        $packing = Packing::with(['user', 'lignes.produit'])->find($id);

        if (!$packing) {
            return $this->responseJson(false, 'Packing non trouvé.', null, 404);
        }

        return $this->responseJson(true, 'Détails du packing.', $packing);
    }
}
