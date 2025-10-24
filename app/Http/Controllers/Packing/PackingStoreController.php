<?php

namespace App\Http\Controllers\Packing;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Packing;
use App\Traits\JsonResponseTrait;
use Illuminate\Validation\ValidationException;

class PackingStoreController extends Controller
{
    use JsonResponseTrait;

    public function store(Request $request)
    {
        try {
            // ✅ nouvelle validation
            $validated = $request->validate([
                'contact_id'      => ['required','exists:contacts,id'],
                'date'            => ['required','date'],
                'shift'           => ['required','in:jour,nuit'],
                'statut'          => ['nullable','in:brouillon,en_cours,validé,annulé'],

                'produit_id'      => ['required','exists:produits,id'],
                'quantite_packed' => ['required','integer','min:1'],
            ]);

            DB::beginTransaction();

            $packing = Packing::create([
                'reference'       => $this->generateReference(),
                'contact_id'      => $validated['contact_id'],
                'date'            => $validated['date'],
                'shift'           => $validated['shift'],
                'statut'          => $validated['statut'] ?? 'brouillon',
                'produit_id'      => $validated['produit_id'],
                'quantite_packed' => $validated['quantite_packed'],
            ]);

            DB::commit();

            return $this->responseJson(true, 'Packing créé avec succès.', 
                $packing->load(['contact','produit']),
                201
            );

        } catch (ValidationException $e) {
            return $this->responseJson(false, 'Erreur de validation.', $e->errors(), 422);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->responseJson(false, 'Erreur serveur lors de la création du packing.', $e->getMessage(), 500);
        }
    }

    protected function generateReference(): string
    {
        $date = now()->format('Ymd');
        $count = Packing::whereDate('created_at', today())->count() + 1;
        return 'PK-' . $date . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
    }
}
