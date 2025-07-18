<?php

namespace App\Http\Controllers\User\Clients;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\JsonResponseTrait;
use Illuminate\Support\Facades\Log;
use Exception;

class DeleteClientController extends Controller
{
    use JsonResponseTrait;

    public function destroy(string $reference)
    {
        try {
            $client = User::where('reference', $reference)
                ->whereHas('roles', fn ($q) => $q->where('name', 'client'))
                ->first();

            if (!$client) {
                return $this->responseJson(false, 'Client introuvable.', null, 404);
            }

            $client->delete();

            return $this->responseJson(true, 'Client supprimé avec succès.');
        } catch (Exception $e) {
            Log::error('Erreur lors de la suppression du client : ' . $e->getMessage());
            return $this->responseJson(false, 'Erreur serveur.', $e->getMessage(), 500);
        }
    }
}
