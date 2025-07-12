<?php

namespace App\Http\Controllers\Roles;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Exception;

class RoleUpdateController extends Controller
{
    use JsonResponseTrait;

    /**
     * Mettre à jour un rôle par son ID.
     */
    public function updateById(Request $request, $id)
    {
        try {
            // Vérifier si le rôle existe
            $role = Role::find($id);
            if (!$role) {
                return $this->responseJson(false, 'Rôle introuvable.', null, 404);
            }

            // Validation du nom uniquement
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:roles,name,' . $id,
            ]);

            $name = strtolower($validated['name']);
            $trigramme = generateTrigramme($name);

            // Vérifier que le trigramme généré est unique (hors rôle actuel)
            $exists = Role::where('trigramme', $trigramme)
                ->where('id', '!=', $id)
                ->exists();

            if ($exists) {
                return $this->responseJson(false, "Le trigramme généré '$trigramme' est déjà utilisé par un autre rôle.", null, 409);
            }

            // Mise à jour du rôle
            $role->update([
                'name' => $name,
                'trigramme' => $trigramme,
            ]);

            return $this->responseJson(true, 'Rôle mis à jour avec succès.', $role);

        } catch (ValidationException $e) {
            return $this->responseJson(false, 'Erreur de validation.', $e->errors(), 422);

        } catch (Exception $e) {
            return $this->responseJson(false, 'Une erreur est survenue lors de la mise à jour du rôle.', $e->getMessage(), 500);
        }
    }
}
