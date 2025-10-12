<?php

namespace App\Http\Controllers\Contacts;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Throwable;

class ContactUpdateController extends Controller
{
    use JsonResponseTrait;

    // PUT /contacts/updateById/{id}
    public function updateById(Request $request, $id)
    {
        try {
            $contact = Contact::findOrFail($id);

            $data = $request->validate([
                'type'     => [Rule::in(['client_specifique','livreur','proprietaire','packing'])],
                'phone'    => ['string','max:30', Rule::unique('contacts','phone')->ignore($contact->id)],
                'nom'      => ['nullable','string','max:120'],
                'prenom'   => ['nullable','string','max:120'],
                'ville'    => ['nullable','string','max:120'],
                'quartier' => ['nullable','string','max:120'],
            ]);

            $newType = $data['type'] ?? $contact->type;

            if (in_array($newType, ['client_specifique','livreur','proprietaire'], true)) {
                foreach (['nom','prenom','ville','quartier'] as $f) {
                    if ($request->has($f) && !filled($request->input($f))) {
                        return $this->responseJson(false, "Le champ {$f} ne peut pas être vide pour le type {$newType}.", null, 422);
                    }
                }
            }

            $contact->update($data);

            return $this->responseJson(true, 'Contact mis à jour.', $contact);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->responseJson(false, 'Échec de validation.', $e->errors(), 422);
        } catch (ModelNotFoundException $e) {
            return $this->responseJson(false, 'Contact introuvable.', null, 404);
        } catch (QueryException $e) {
            Log::error('Contact update failed', ['id' => $id, 'error' => $e->getMessage()]);
            return $this->responseJson(false, 'Erreur lors de la mise à jour du contact.', null, 500);
        } catch (Throwable $e) {
            Log::error('Contact update unexpected', ['id' => $id, 'error' => $e->getMessage()]);
            return $this->responseJson(false, 'Erreur inattendue.', null, 500);
        }
    }
}
