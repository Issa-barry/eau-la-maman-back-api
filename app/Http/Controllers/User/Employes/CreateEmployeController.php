<?php

namespace App\Http\Controllers\User\Employes;

use App\Http\Controllers\Controller;
use App\Models\Adresse;
use App\Models\User;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Exception;

class CreateEmployeController extends Controller
{
    use JsonResponseTrait;

    /**
     * POST /api/users/employes
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'nom_complet'    => 'required|string|max:255',
                'phone'          => 'required|string|unique:users,phone',
                'email'          => 'required|email|unique:users,email', // <- email requis pour employé
                'civilite'       => 'nullable|in:Mr,Mme,Mlle,Autre',
                'date_naissance' => 'nullable|date',
                'agence_id'      => 'nullable|integer',

                // Mot de passe OBLIGATOIRE pour employé
                'password'               => 'required|string|min:8|confirmed',
                'password_confirmation'  => 'required|string|min:8',

                // Rôle : on accepte role_id OU role_name
                'role_id'    => 'nullable|integer',
                'role_name'  => 'nullable|string',

                // Adresse (optionnelle dans la requête, mais on en crée UNE de toute façon)
                'adresse'                    => 'nullable|array',
                'adresse.pays'               => 'nullable|string|max:255',
                'adresse.adresse'            => 'nullable|string|max:255',
                'adresse.complement_adresse' => 'nullable|string|max:255',
                'adresse.ville'              => 'nullable|string|max:255',
                'adresse.quartier'           => 'nullable|string|max:255',
                'adresse.code_postal'        => 'nullable|string|max:20',
                'adresse.region'             => 'nullable|string|max:255',
            ]);

            // ---- Rôle (obligatoire via id ou name) ----
            $role = null;
            if (!empty($validated['role_id'])) {
                $role = Role::find($validated['role_id']);
            } elseif (!empty($validated['role_name'])) {
                $role = Role::where('name', $validated['role_name'])->first();
            }

            if (!$role) {
                return $this->responseJson(false, 'Rôle invalide ou introuvable. Fournis role_id ou role_name.', null, 422);
            }

            // --- Adresse : en créer UNE toujours ---
            $adressePayload = $validated['adresse'] ?? [];
            if (!array_key_exists('pays', $adressePayload) || trim((string)($adressePayload['pays'] ?? '')) === '') {
                $adressePayload['pays'] = 'Guinee-Conakry';
            }
            $adresse = Adresse::create($adressePayload);

            // --- Création de l'employé ---
            $user = User::create([
                'nom_complet'    => $validated['nom_complet'],
                'phone'          => $validated['phone'],
                'email'          => $validated['email'],
                'civilite'       => $validated['civilite'] ?? 'Autre',
                'date_naissance' => $validated['date_naissance'] ?? '9999-12-31',
                'adresse_id'     => $adresse->id,
                'agence_id'      => $validated['agence_id'] ?? null,

                // Rôle
                'role_id'        => $role->id,

                // Mot de passe OBLIGATOIRE (hashé)
                'password'       => Hash::make($validated['password']),

                // Pour les employés, on ne gère pas type_client / type_vehicule
                // (laisse par défaut côté modèle/migration si présents)
            ]);

            // Spatie: associe le rôle (même si role_id est renseigné)
            $user->assignRole($role->name);

            // (Optionnel) Envoyer un email de vérification si tu le souhaites
            $user->sendEmailVerificationNotification();

            return $this->responseJson(true, 'Employé créé avec succès.', $user->load('adresse'), 201);

        } catch (ValidationException $e) {
            return $this->responseJson(false, 'Erreur de validation.', $e->errors(), 422);
        } catch (QueryException $e) {
            Log::error('Erreur SQL lors de la création de l’employé : ' . $e->getMessage());
            return $this->responseJson(false, 'Erreur base de données.', null, 500);
        } catch (Exception $e) {
            Log::error('Erreur serveur lors de la création de l’employé : ' . $e->getMessage());
            return $this->responseJson(false, 'Erreur serveur.', $e->getMessage(), 500);
        }
    }
}
