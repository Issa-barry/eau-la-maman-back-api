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
            // listes autorisées depuis le modèle User
            $clientTypes   = [User::TYPE_CLIENT_SPECIFIQUE, User::TYPE_CLIENT_VEHICULE];
            $vehiculeTypes = [User::VEHICULE_CAMION, User::VEHICULE_FOURGONETTE, User::VEHICULE_TRICYCLE];

            $validated = $request->validate([
                'nom_complet'    => 'required|string|max:255',
                'phone'          => 'required|string|unique:users,phone,' . $id,
                'email'          => 'nullable|email|unique:users,email,' . $id,
                'civilite'       => 'nullable|in:Mr,Mme,Mlle,Autre',
                'date_naissance' => 'nullable|date',

                // adresse (optionnelle dans la requête)
                'adresse'                    => 'nullable|array',
                'adresse.pays'               => 'nullable|string|max:255',
                'adresse.adresse'            => 'nullable|string|max:255',
                'adresse.complement_adresse' => 'nullable|string|max:255',
                'adresse.ville'              => 'nullable|string|max:255',
                'adresse.quartier'           => 'nullable|string|max:255',
                'adresse.code_postal'        => 'nullable|string|max:20',

                // type client / véhicule
                'type_client'   => 'nullable|in:' . implode(',', $clientTypes),
                'type_vehicule' => 'nullable|required_if:type_client,' . User::TYPE_CLIENT_VEHICULE
                                    . '|in:' . implode(',', $vehiculeTypes),
            ]);

            $user = User::with('adresse')->find($id);
            if (!$user) {
                return $this->responseJson(false, 'Client introuvable.', null, 404);
            }

            // ---------------- Adresse ----------------
            if ($request->has('adresse')) {
                // une adresse a été fournie (même vide) -> on (up)date en forçant le pays par défaut si absent
                $adrData = $validated['adresse'] ?? [];
                if (!array_key_exists('pays', $adrData) || trim((string)($adrData['pays'] ?? '')) === '') {
                    $adrData['pays'] = 'Guinee-Conakry';
                }

                if ($user->adresse) {
                    $user->adresse->update($adrData);
                } else {
                    $adresse = Adresse::create($adrData);
                    $user->adresse_id = $adresse->id;
                }
            } else {
                // aucune adresse dans la requête : si le client n’en a pas, on en crée une par défaut
                if (!$user->adresse) {
                    $adresse = Adresse::create(['pays' => 'Guinee-Conakry']);
                    $user->adresse_id = $adresse->id;
                }
            }

            // ---------------- Champs simples ----------------
            $typeClient = $validated['type_client'] ?? $user->type_client ?? User::TYPE_CLIENT_SPECIFIQUE;

            $payload = [
                'nom_complet'    => $validated['nom_complet'],
                'phone'          => $validated['phone'],
                'email'          => $validated['email'] ?? $user->email,
                'civilite'       => $validated['civilite'] ?? $user->civilite ?? 'Autre',
                'date_naissance' => $validated['date_naissance'] ?? $user->date_naissance ?? '9999-12-31',

                'type_client'    => $typeClient,
                'type_vehicule'  => $typeClient === User::TYPE_CLIENT_VEHICULE
                    ? ($validated['type_vehicule'] ?? $user->type_vehicule)
                    : null,
            ];

            $user->fill($payload)->save();

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
