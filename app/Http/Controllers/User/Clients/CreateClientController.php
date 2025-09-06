<?php

namespace App\Http\Controllers\User\Clients;

use App\Http\Controllers\Controller;
use App\Models\Adresse;
use App\Models\Role;
use App\Models\User;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Exception;

class CreateClientController extends Controller
{
    use JsonResponseTrait;

    public function store(Request $request)
    {
        try {
            // Listes autorisées depuis le modèle User
            $clientTypes   = [User::TYPE_CLIENT_SPECIFIQUE, User::TYPE_CLIENT_VEHICULE];
            $vehiculeTypes = [User::VEHICULE_CAMION, User::VEHICULE_FOURGONETTE, User::VEHICULE_TRICYCLE];

            $validated = $request->validate([
                'nom_complet'    => 'required|string|max:255',
                'phone'          => 'required|string|unique:users,phone',
                'email'          => 'nullable|email|unique:users,email',
                'civilite'       => 'nullable|in:Mr,Mme,Mlle,Autre',
                'date_naissance' => 'nullable|date',

                // Adresse (optionnelle dans la requête, mais on en crée UNE toujours)
                'adresse'                    => 'nullable|array',
                'adresse.pays'               => 'nullable|string|max:255',
                'adresse.adresse'            => 'nullable|string|max:255',
                'adresse.complement_adresse' => 'nullable|string|max:255',
                'adresse.ville'              => 'nullable|string|max:255',
                'adresse.quartier'           => 'nullable|string|max:255',
                'adresse.code_postal'        => 'nullable|string|max:20',

                // Type client / véhicule
                'type_client'   => 'nullable|in:' . implode(',', $clientTypes),
                'type_vehicule' => 'nullable|required_if:type_client,' . User::TYPE_CLIENT_VEHICULE
                                   . '|in:' . implode(',', $vehiculeTypes),
            ]);

            // Rôle "client"
            $role = Role::where('name', 'client')->first();
            if (!$role) {
                return $this->responseJson(false, 'Rôle client introuvable.', null, 404);
            }

            // --- Adresse : EN CRÉER UNE TOUJOURS ---
            // Si rien n'est envoyé, on part d'un tableau vide puis on force le pays par défaut.
            $adressePayload = $validated['adresse'] ?? [];

            // Si pays absent ou vide -> valeur par défaut
            if (!array_key_exists('pays', $adressePayload) || trim((string)($adressePayload['pays'] ?? '')) === '') {
                $adressePayload['pays'] = 'Guinee-Conakry';
            }

            $adresse = Adresse::create($adressePayload);

            // --- Données du client ---
            $typeClient = $validated['type_client'] ?? User::TYPE_CLIENT_SPECIFIQUE;

            $payload = [
                'nom_complet'    => $validated['nom_complet'],
                'phone'          => $validated['phone'],
                'email'          => $validated['email'] ?? null,
                'civilite'       => $validated['civilite'] ?? 'Autre',
                'date_naissance' => $validated['date_naissance'] ?? '9999-12-31',
                'adresse_id'     => $adresse->id,          // <-- toujours rattaché à une adresse
                'role_id'        => $role->id,
                'password'       => '',                    // clients sans mot de passe

                'type_client'    => $typeClient,
                'type_vehicule'  => $typeClient === User::TYPE_CLIENT_VEHICULE
                                    ? ($validated['type_vehicule'] ?? null)
                                    : null,
            ];

            $user = User::create($payload);
            $user->assignRole('client');

            return $this->responseJson(true, 'Client créé avec succès.', $user->load('adresse'), 201);

        } catch (ValidationException $e) {
            return $this->responseJson(false, 'Erreur de validation.', $e->errors(), 422);
        } catch (QueryException $e) {
            Log::error('Erreur SQL lors de la création du client : ' . $e->getMessage());
            return $this->responseJson(false, 'Erreur base de données.', null, 500);
        } catch (Exception $e) {
            Log::error('Erreur serveur lors de la création du client : ' . $e->getMessage());
            return $this->responseJson(false, 'Erreur serveur.', $e->getMessage(), 500);
        }
    }
}
