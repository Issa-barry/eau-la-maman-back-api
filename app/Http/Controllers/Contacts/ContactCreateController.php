<?php

namespace App\Http\Controllers\Contacts;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Vehicule;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

class ContactCreateController extends Controller
{
    use JsonResponseTrait;

    // POST /contacts/create
    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'type'                     => ['required', Rule::in(['client_specifique','livreur','proprietaire','packing'])],
                'phone'                    => ['required','string','max:30','unique:contacts,phone'],
                'nom'                      => ['nullable','string','max:120'],
                'prenom'                   => ['nullable','string','max:120'],
                'ville'                    => ['nullable','string','max:120'],
                'quartier'                 => ['nullable','string','max:120'],

                // Rattachement véhicule (optionnel sauf pour un livreur)
                'vehicule_id'              => ['nullable','integer','exists:vehicules,id'],
                'vehicule_immatriculation' => ['nullable','string','max:60','exists:vehicules,immatriculation'],
            ]);

            // Champs requis selon le type
            if (in_array($data['type'], ['client_specifique','livreur','proprietaire'], true)) {
                foreach (['nom','prenom','ville','quartier'] as $f) {
                    if (empty($data[$f])) {
                        return $this->responseJson(false, "Le champ {$f} est requis pour le type {$data['type']}.", null, 422);
                    }
                }
            }

            // Pour un livreur : véhicule obligatoire
            if ($data['type'] === Contact::TYPE_LIVREUR) {
                if (empty($data['vehicule_id']) && empty($data['vehicule_immatriculation'])) {
                    return $this->responseJson(false, "Un livreur doit être rattaché à un véhicule (vehicule_id ou vehicule_immatriculation).", null, 422);
                }

                // Si seule l'immatriculation est fournie, on résout l'ID
                if (!empty($data['vehicule_immatriculation']) && empty($data['vehicule_id'])) {
                    $vehiculeId = Vehicule::where('immatriculation', $data['vehicule_immatriculation'])->value('id');
                    if (!$vehiculeId) {
                        return $this->responseJson(false, "Aucun véhicule trouvé pour l’immatriculation fournie.", null, 422);
                    }
                    $data['vehicule_id'] = $vehiculeId;
                }
            }

            // Ne pas persister le champ technique
            unset($data['vehicule_immatriculation']);

            $contact = Contact::create($data);

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
