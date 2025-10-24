<?php

namespace App\Http\Controllers\Contacts;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Traits\JsonResponseTrait;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;
use Exception;

class ContactDeleteController extends Controller
{
    use JsonResponseTrait;

    /**
     * DELETE /contacts/deleteByReference/{reference}
     *
     * Supprime un contact via sa référence unique.
     */
    public function deleteByReference(string $reference)
    {
        try {
            //  Validation manuelle simple
            if (empty($reference) || strlen($reference) < 3) {
                throw ValidationException::withMessages([
                    'reference' => ['Référence invalide.'],
                ]);
            }

            //  Recherche du contact
            $contact = Contact::where('reference', $reference)->firstOrFail();

            //  Suppression
            $contact->delete();

            return $this->responseJson(true, 'Contact supprimé avec succès.');
        }

        // 🔹 Cas : référence inexistante
        catch (ModelNotFoundException $e) {
            return $this->responseJson(false, 'Aucun contact trouvé pour cette référence.', null, 404);
        }

        // 🔹 Cas : référence mal formée
        catch (ValidationException $e) {
            return $this->responseJson(false, 'Données invalides.', $e->errors(), 422);
        }

        //  Cas : contrainte d’intégrité (clé étrangère, etc.)
        catch (QueryException $e) {
            $message = 'Erreur base de données lors de la suppression du contact.';
            if (str_contains($e->getMessage(), 'foreign key')) {
                $message = 'Impossible de supprimer ce contact car il est lié à d’autres enregistrements.';
            }

            Log::error('Suppression contact - erreur SQL', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);

            return $this->responseJson(false, $message, null, 500);
        }

        // 🔹 Cas : toute autre erreur logique
        catch (Exception $e) {
            Log::warning('Erreur logique suppression contact', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);

            return $this->responseJson(false, 'Erreur lors du traitement de la requête.', null, 500);
        }

        // 🔹 Cas : erreur inattendue (niveau système, fatale, etc.)
        catch (Throwable $e) {
            Log::critical('Erreur critique suppression contact', [
                'reference' => $reference,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->responseJson(false, 'Erreur interne du serveur.', null, 500);
        }
    }
}


