<?php

namespace App\Http\Controllers\User\Clients;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\JsonResponseTrait;

class ShowClientController extends Controller
{
    use JsonResponseTrait;

     public function index()
    {
        $clients = User::with('adresse')
            ->whereHas('roles', fn ($q) => $q->where('name', 'client'))
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->responseJson(true, 'Liste des clients récupérée avec succès.', $clients);
    }

     public function showByReference(string $reference)
    {
        $client = User::with(['adresse', 'agence'])
            ->where('reference', $reference)
            ->whereHas('roles', fn ($q) => $q->where('name', 'client'))
            ->first();

        if (!$client) {
            return $this->responseJson(false, 'Client introuvable.', null, 404);
        }

        return $this->responseJson(true, 'Client récupéré avec succès.', $client);
    }
}
