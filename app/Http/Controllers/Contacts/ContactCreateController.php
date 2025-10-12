<?php

namespace App\Http\Controllers\Contacts;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Throwable;

class ContactCreateController extends Controller
{
    use JsonResponseTrait;

    // POST /contacts/create
    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'type'     => ['required', Rule::in(['client_specifique','livreur','proprietaire','packing'])],
                'phone'    => ['required','string','max:30','unique:contacts,phone'],
                'nom'      => ['nullable','string','max:120'],
                'prenom'   => ['nullable','string','max:120'],
                'ville'    => ['nullable','string','max:120'],
                'quartier' => ['nullable','string','max:120'],
            ]);

            if (in_array($data['type'], ['client_specifique','livreur','proprietaire'], true)) {
                foreach (['nom','prenom','ville','quartier'] as $f) {
                    if (empty($data[$f])) {
                        return $this->responseJson(false, "Le champ {$f} est requis pour le type {$data['type']}.", null, 422);
                    }
                }
            }

            $contact = Contact::create($data);

            return $this->responseJson(true, 'Contact créé.', $contact, 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->responseJson(false, 'Échec de validation.', $e->errors(), 422);
        } catch (QueryException $e) {
            Log::error('Contact create failed', ['error' => $e->getMessage()]);
            return $this->responseJson(false, 'Erreur lors de la création du contact.', null, 500);
        } catch (Throwable $e) {
            Log::error('Contact create unexpected', ['error' => $e->getMessage()]);
            return $this->responseJson(false, 'Erreur inattendue.', null, 500);
        }
    }
}
