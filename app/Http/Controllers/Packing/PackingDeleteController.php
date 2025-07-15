<?php

namespace App\Http\Controllers\Packing;

use App\Http\Controllers\Controller;
use App\Models\Packing;
use App\Traits\JsonResponseTrait;

class PackingDeleteController extends Controller
{
    use JsonResponseTrait;

    public function __invoke(int $id)
    {
        $packing = Packing::find($id);

        if (!$packing) {
            return $this->responseJson(false, 'Packing introuvable.', null, 404);
        }

        $packing->delete();
        return $this->responseJson(true, 'Packing supprimé.');
    }
}
