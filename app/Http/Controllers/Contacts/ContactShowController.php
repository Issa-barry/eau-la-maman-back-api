<?php

namespace App\Http\Controllers\Contacts;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class ContactShowController extends Controller
{
    use JsonResponseTrait;

    // GET /contacts/all?type=&search=&per_page=
    public function index(Request $r)
    {
        try {
            $r->validate([
                'type'     => 'nullable|in:client_specifique,livreur,proprietaire,packing',
                'search'   => 'nullable|string|max:100',
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            $q = Contact::query()
                ->with(['vehicule:id,type,immatriculation,nom_proprietaire,prenom_proprietaire,phone_proprietaire']); // ✅

            if ($r->filled('type')) {
                $q->where('type', $r->input('type'));
            }

            if ($search = trim((string) $r->input('search', ''))) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('nom', 'like', "%{$search}%")
                        ->orWhere('prenom', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('ville', 'like', "%{$search}%")
                        ->orWhere('quartier', 'like', "%{$search}%")
                        ->orWhere('reference', 'like', "%{$search}%");
                });
            }

            $perPage = (int) $r->input('per_page', 15);
            $perPage = ($perPage < 1 || $perPage > 100) ? 15 : $perPage;

            return $this->responseJson(true, 'Liste des contacts.', $q->latest()->paginate($perPage));
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->responseJson(false, 'Échec de validation.', $e->errors(), 422);
        } catch (\Illuminate\Database\QueryException $e) {
            \Log::error('Contacts index query error', ['error' => $e->getMessage()]);
            return $this->responseJson(false, 'Erreur lors de la récupération des contacts.', null, 500);
        } catch (\Throwable $e) {
            \Log::error('Contacts index unexpected error', ['error' => $e->getMessage()]);
            return $this->responseJson(false, 'Erreur inattendue.', null, 500);
        }
    }

    public function getById($id)
    {
        try {
            $contact = Contact::with(['vehicule:id,type,immatriculation,nom_proprietaire,prenom_proprietaire,phone_proprietaire'])
                ->findOrFail($id); // ✅
            return $this->responseJson(true, 'Détail du contact.', $contact);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->responseJson(false, 'Contact introuvable.', null, 404);
        } catch (\Illuminate\Database\QueryException $e) {
            \Log::error('Contact show query error', ['id' => $id, 'error' => $e->getMessage()]);
            return $this->responseJson(false, 'Erreur lors de la récupération du contact.', null, 500);
        } catch (\Throwable $e) {
            \Log::error('Contact show unexpected error', ['id' => $id, 'error' => $e->getMessage()]);
            return $this->responseJson(false, 'Erreur inattendue.', null, 500);
        }
    }
}
