<?php

namespace App\Http\Controllers\Contacts;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Throwable;

class ContactIndexShowController extends Controller
{
    use JsonResponseTrait;

    // GET /contacts?search=&type=&per_page=
    public function index(Request $r)
    {
        try {
            $r->validate([
                'search'   => 'nullable|string|max:100',
                'type'     => 'nullable|in:client_specifique,livreur,proprietaire,packing',
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            $q = Contact::query();

            $type = trim((string) $r->input('type', ''));
            if ($type !== '') {
                $q->where('type', $type);
            }

            $search = trim((string) $r->input('search', ''));
            if ($search !== '') {
                $q->where(function ($qq) use ($search) {
                    $qq->where('nom', 'like', "%{$search}%")
                       ->orWhere('prenom', 'like', "%{$search}%")
                       ->orWhere('phone', 'like', "%{$search}%")
                       ->orWhere('ville', 'like', "%{$search}%")
                       ->orWhere('quartier', 'like', "%{$search}%");
                });
            }

            $perPage = (int) $r->input('per_page', 15);
            if ($perPage < 1 || $perPage > 100) {
                $perPage = 15;
            }

            $contacts = $q->latest()->paginate($perPage);

            return $this->responseJson(true, 'Liste des contacts.', $contacts);
        } catch (ValidationException $e) {
            return $this->responseJson(false, 'Échec de validation.', $e->errors(), 422);
        } catch (QueryException $e) {
            Log::error('Contacts index query error', ['error' => $e->getMessage()]);
            return $this->responseJson(false, 'Erreur lors de la récupération des contacts.', null, 500);
        } catch (Throwable $e) {
            Log::error('Contacts index unexpected error', ['error' => $e->getMessage()]);
            return $this->responseJson(false, 'Erreur inattendue.', null, 500);
        }
    }

    // GET /contacts/{id}
    public function show($id)
    {
        try {
            $contact = Contact::findOrFail($id);
            return $this->responseJson(true, 'Détail du contact.', $contact);
        } catch (ModelNotFoundException $e) {
            return $this->responseJson(false, 'Contact introuvable.', null, 404);
        } catch (QueryException $e) {
            Log::error('Contact show query error', ['id' => $id, 'error' => $e->getMessage()]);
            return $this->responseJson(false, 'Erreur lors de la récupération du contact.', null, 500);
        } catch (Throwable $e) {
            Log::error('Contact show unexpected error', ['id' => $id, 'error' => $e->getMessage()]);
            return $this->responseJson(false, 'Erreur inattendue.', null, 500);
        }
    }
}
