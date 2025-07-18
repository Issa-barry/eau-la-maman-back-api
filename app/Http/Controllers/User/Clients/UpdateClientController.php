<?php

namespace App\Http\Controllers\User\Clients;

use App\Http\Controllers\Controller;
use App\Models\Adresse;
use App\Models\User;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Exception;

class UpdateClientController extends Controller
{
    use JsonResponseTrait;

    public function update(Request $request, int $id)
    {
        try {
            $validated = $request->validate([
                'nom_complet'    => 'required|string|max:255',
                'phone'          => 'required|string|unique:users,phone,' . $id,
                'email'          => 'nullable|email|unique:users,email,' . $id,
                'civilite'       => 'nullable|in:Mr,Mme,Mlle,Autre',
                'date_naissance' => 'nullable|date',
                'adresse'        => 'nullable|array',
                'adresse.pays'               => 'nullable|string|max:255',
                'adresse.adresse'           => 'nullable|string|max:255',
                'adresse.complement_adresse'=> 'nullable|string|max:255',
                'adresse.ville'             => 'nullable|string|max:255',
                'adresse.quartier'          => 'nullable|string|max:255',
                'adresse.code_postal'       => 'nullable|string|max:20',
            ]);

            $user = User::with('adresse')->find($id);
            if (!$user) {
                return $this->responseJson(false, 'Client introuvable.', null, 404);
            }

            if (!empty($validated['adresse'])) {
                if ($user->adresse) {
                    $user->adresse->update($validated['adresse']);
                } else {
                    $adresse = Adresse::create($validated['adresse']);
                    $user->adresse_id = $adresse->id;
                }
            }

            $user->update([
                'nom_complet'    => $validated['nom_complet'],
                'phone'          => $validated['phone'],
                'email'          => $validated['email'] ?? null,
                'civilite'       => $validated['civilite'] ?? 'Autre',
                'date_naissance' => $validated['date_naissance'] ?? '9999-12-31',
            ]);

            return $this->responseJson(true, 'Client mis à jour avec succès.', $user->load('adresse'));

        } catch (ValidationException $e) {
            return $this->responseJson(false, 'Erreur de validation.', $e->errors(), 422);
        } catch (QueryException $e) {
            Log::error('Erreur SQL lors de la mise à jour du client : ' . $e->getMessage());
            return $this->responseJson(false, 'Erreur base de données.', null, 500);
        } catch (Exception $e) {
            Log::error('Erreur serveur lors de la mise à jour du client : ' . $e->getMessage());
            return $this->responseJson(false, 'Erreur serveur.', $e->getMessage(), 500);
        }
    }
}
