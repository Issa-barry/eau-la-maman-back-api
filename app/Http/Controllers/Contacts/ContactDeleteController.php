<?php

namespace App\Http\Controllers\Contacts;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Traits\JsonResponseTrait;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Throwable;

class ContactDeleteController extends Controller
{
    use JsonResponseTrait;

    // DELETE /contacts/deleteById/{id}
    public function deleteById($id)
    {
        try {
            $contact = Contact::findOrFail($id);
            $contact->delete();

            return $this->responseJson(true, 'Contact supprimé.');
        } catch (ModelNotFoundException $e) {
            return $this->responseJson(false, 'Contact introuvable.', null, 404);
        } catch (QueryException $e) {
            Log::error('Contact delete failed', ['id' => $id, 'error' => $e->getMessage()]);
            return $this->responseJson(false, 'Erreur lors de la suppression du contact.', null, 500);
        } catch (Throwable $e) {
            Log::error('Contact delete unexpected', ['id' => $id, 'error' => $e->getMessage()]);
            return $this->responseJson(false, 'Erreur inattendue.', null, 500);
        }
    }
}
