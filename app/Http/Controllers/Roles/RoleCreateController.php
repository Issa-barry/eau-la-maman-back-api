<?php

namespace App\Http\Controllers\Roles;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Traits\JsonResponseTrait;

class RoleCreateController extends Controller
{
    use JsonResponseTrait;

    /**
     * Créer un nouveau rôle avec trigramme généré automatiquement
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:roles,name',
            ]);

            $name = strtolower($validated['name']);
            $trigramme = generateTrigramme($name);

            // Vérifie que le trigramme n'existe pas déjà
            if (Role::where('trigramme', $trigramme)->exists()) {
                return $this->responseJson(false, "Le trigramme généré '$trigramme' est déjà utilisé. Choisissez un autre nom.", null, 409);
            }

            $role = Role::create([
                'name' => $name,
                'guard_name' => 'web',
                'trigramme' => $trigramme,
            ]);

            return $this->responseJson(true, 'Rôle créé avec succès.', $role, 201);

        } catch (ValidationException $e) {
            return $this->responseJson(false, 'Erreur de validation.', $e->errors(), 422);

        } catch (\Exception $e) {
            return $this->responseJson(false, 'Une erreur est survenue lors de la création du rôle.', $e->getMessage(), 500);
        }
    }
}
