<?php

namespace App\Http\Controllers\User\Employes;

use App\Http\Controllers\Controller;
use App\Models\Adresse;
use App\Models\User;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        // ✅ Validation : email & password requis pour un employé
        // On autorise EITHER (prenom + nom) OR (nom_complet)
        try {
            $validated = $request->validate([
                // Identité
                'prenom'        => ['nullable','string','max:100'],
                'nom'           => ['nullable','string','max:100'],
                'nom_complet'   => ['nullable','string','max:255'],

                // Coordonnées & infos compte
                'phone'         => ['required','string','max:50','unique:users,phone'],
                'email'         => ['required','email:rfc,dns','unique:users,email'],
                'civilite'      => ['nullable','in:Mr,Mme,Mlle,Autre'],
                'date_naissance'=> ['nullable','date'],
                'agence_id'     => ['nullable','integer'],

                // Mot de passe OBLIGATOIRE pour employé
                'password'              => ['required','string','min:8','confirmed'],
                'password_confirmation' => ['required','string','min:8'],

                // Rôle : on accepte role_id OU role_name (un des deux doit exister)
                'role_id'   => ['nullable','integer'],
                'role_name' => ['nullable','string','max:190'],

                // Adresse (facultative dans la requête, mais on en créera une)
                'adresse'                    => ['nullable','array'],
                'adresse.pays'               => ['nullable','string','max:255'],
                'adresse.adresse'            => ['nullable','string','max:255'],
                'adresse.complement_adresse' => ['nullable','string','max:255'],
                'adresse.ville'              => ['nullable','string','max:255'],
                'adresse.quartier'           => ['nullable','string','max:255'],
                'adresse.code_postal'        => ['nullable','string','max:20'],
                'adresse.region'             => ['nullable','string','max:255'],
            ]);

            // 🔐 Règle métier : il faut (prenom + nom) OU nom_complet
            $hasParts = filled($validated['prenom'] ?? null) && filled($validated['nom'] ?? null);
            $hasFull  = filled($validated['nom_complet'] ?? null);

            if (!$hasParts && !$hasFull) {
                return $this->responseJson(false, "Renseigne soit 'prenom' et 'nom', soit 'nom_complet'.", null, 422);
            }

            // 🎭 Résoudre le nom complet -> découper si besoin
            $prenom = $validated['prenom'] ?? null;
            $nom    = $validated['nom'] ?? null;

            if (!$hasParts && $hasFull) {
                $full   = trim($validated['nom_complet']);
                $pieces = preg_split('/\s+/', $full);
                $nom    = $pieces ? array_pop($pieces) : null;
                $prenom = $pieces ? trim(implode(' ', $pieces)) : null;
            }

            // 🎭 Civilité par défaut
            $civilite = $validated['civilite'] ?? 'Autre';

            // 🎯 Rôle (obligatoire via id OU name)
            $role = null;
            if (!empty($validated['role_id'])) {
                $role = Role::find($validated['role_id']);
            } elseif (!empty($validated['role_name'])) {
                $role = Role::where('name', $validated['role_name'])->first();
            }
            if (!$role) {
                return $this->responseJson(false, 'Rôle invalide ou introuvable. Fournis role_id ou role_name.', null, 422);
            }

            // ✅ Transaction : adresse + user + assignation rôle
            return DB::transaction(function () use ($validated, $prenom, $nom, $civilite, $role) {
                // 🏠 Adresse : on en crée une (avec défaut pays si vide)
                $adressePayload = $validated['adresse'] ?? [];
                if (!array_key_exists('pays', $adressePayload) || trim((string)($adressePayload['pays'] ?? '')) === '') {
                    $adressePayload['pays'] = 'Guinee-Conakry';
                }
                $adresse = Adresse::create($adressePayload);

                // 👤 Création employé
                $user = User::create([
                    'prenom'        => $prenom,
                    'nom'           => $nom,
                    'phone'         => $validated['phone'],
                    'email'         => $validated['email'],
                    'civilite'      => $civilite,
                    'date_naissance'=> $validated['date_naissance'] ?? null,
                    'adresse_id'    => $adresse->id,
                    'agence_id'     => $validated['agence_id'] ?? null,
                    'role_id'       => $role->id,
                    'statut'        => 'attente', // par défaut
                    'password'      => Hash::make($validated['password']),
                ]);

                // 🔗 Spatie : assigner le rôle (en plus du role_id stocké)
                $user->assignRole($role->name);

                // (Optionnel) Email de vérification
                $user->sendEmailVerificationNotification();

                return $this->responseJson(true, 'Employé créé avec succès.', $user->load('adresse'), 201);
            });

        } catch (ValidationException $e) {
            return $this->responseJson(false, 'Erreur de validation.', $e->errors(), 422);
        } catch (QueryException $e) {
            Log::error('SQL - création employé : '.$e->getMessage());
            return $this->responseJson(false, 'Erreur base de données.', null, 500);
        } catch (Exception $e) {
            Log::error('Serveur - création employé : '.$e->getMessage());
            return $this->responseJson(false, 'Erreur serveur.', $e->getMessage(), 500);
        }
    }
}
