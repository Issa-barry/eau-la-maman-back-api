<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Throwable;

class MeController extends Controller
{
    use JsonResponseTrait;

    /**
     * GET /api/v1/users/me
     * Retourne les informations du user authentifié (Sanctum).
     */
    public function show(Request $request)
    {
        try {
            $user = $request->user(); // équiv. auth()->user()

            if (!$user) {
                // au cas où le middleware ne serait pas appliqué
                return $this->responseJson(false, 'Non authentifié.', null, 401);
            }

            // Charge les relations utiles
            $user->load([
                'adresse',              // App\Models\Adresse
                'agence',               // App\Models\Agence (si liée)
                'roles:id,name',        // Spatie roles
            ]);

            // Prépare une vue "propre" du user
            $data = [
                'id'             => $user->id,
                'reference'      => $user->reference,
                'email'          => $user->email,
                'email_verified' => (bool) $user->email_verified_at,
                'civilite'       => $user->civilite,
                'nom_complet'    => $user->nom_complet,
                'phone'          => $user->phone,
                'date_naissance' => $user->date_naissance,
                'type_client'    => $user->type_client,    // 'specifique' | 'vehicule'
                'type_vehicule'  => $user->type_vehicule,  // null si 'specifique'
                'role'           => optional($user->roles->first())->only(['id', 'name']),
                'adresse'        => $user->adresse,        // objet Adresse
                'agence'         => $user->agence,         // objet Agence (ou null)
                'created_at'     => $user->created_at,
                'updated_at'     => $user->updated_at,
            ];

            return $this->responseJson(true, 'Profil utilisateur.', $data);
        } catch (QueryException $e) {
            Log::error('Erreur SQL (users.me): ' . $e->getMessage());
            return $this->responseJson(false, 'Erreur base de données.', null, 500);
        } catch (Throwable $e) {
            Log::error('Erreur inattendue (users.me): ' . $e->getMessage());
            return $this->responseJson(false, 'Erreur inattendue.', null, 500);
        }
    }
}
