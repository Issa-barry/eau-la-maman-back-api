<?php

namespace App\Http\Controllers\Contacts;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

class ContactCreateController extends Controller
{
    use JsonResponseTrait;

    // POST /contacts
    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'type'     => ['required', Rule::in([Contact::TYPE_CLIENT_SPECIFIQUE, Contact::TYPE_PACKING])],
                'phone'    => ['required','string','max:30','unique:contacts,phone'],
                'nom'      => ['required','string','max:120'],
                'prenom'   => ['required','string','max:120'],
                'ville'    => ['nullable','string','max:120'],
                'quartier' => ['nullable','string','max:120'],
            ]);

        
            $contact = Contact::create($data); // reference auto via modèle

            return $this->responseJson(true, 'Contact créé.', $contact, 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->responseJson(false, 'Échec de validation.', $e->errors(), 422);

        } catch (QueryException $e) {
            Log::error('Contact create failed', ['error' => $e->getMessage()]);
            return $this->responseJson(false, 'Erreur lors de la création du contact.', null, 500);

        } catch (\Throwable $e) {
            Log::error('Contact create unexpected', ['error' => $e->getMessage()]);
            return $this->responseJson(false, 'Erreur inattendue.', null, 500);
        }
    }
}
