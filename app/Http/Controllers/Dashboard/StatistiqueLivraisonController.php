<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Livraison;
use App\Traits\JsonResponseTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StatistiqueLivraisonController extends Controller
{
    use JsonResponseTrait;

    /**
     * GET /api/dashboards/statistiques/livraisons
     * ?periode=aujourdhui|cette_semaine|ce_mois|cette_annee
     * &date_from=YYYY-MM-DD&date_to=YYYY-MM-DD
     */
    public function index(Request $r)
    {
        try {
            $v = $r->validate([
                'periode'   => 'nullable|in:aujourdhui,cette_semaine,ce_mois,cette_annee',
                'date_from' => 'nullable|date',
                'date_to'   => 'nullable|date',
            ]);

            [$from, $to] = $this->resolveRange($v);

            // Sécurité: table existante ?
            $livTable = (new Livraison())->getTable(); // "livraisons"
            if (!Schema::hasTable($livTable)) {
                return $this->responseJson(true, 'Stats livraisons', [
                    'range' => ['from' => $from?->toDateString(), 'to' => $to?->toDateString()],
                    'livraisons' => [
                        'count'               => 0,
                        'quantite_totale'     => 0,
                        'montant_total_paye'  => 0.0,
                    ],
                ]);
            }

            // Colonne de date : date_livraison | date | created_at
            $dateCol = Schema::hasColumn($livTable, 'date_livraison')
                ? 'date_livraison'
                : (Schema::hasColumn($livTable, 'date') ? 'date' : 'created_at');

            $base = Livraison::query();
            if ($from) $base->whereDate($dateCol, '>=', $from);
            if ($to)   $base->whereDate($dateCol, '<=', $to);

            // Nombre de livraisons
            $count = (clone $base)->count();

            // Quantité: si "quantite_livree" existe on l'utilise, sinon on somme les lignes
            $hasQuantLivree = Schema::hasColumn($livTable, 'quantite_livree');
            if ($hasQuantLivree) {
                $quantiteTotale = (int) (clone $base)->sum('quantite_livree');
            } else {
                // fallback via livraison_lignes
                $ids = (clone $base)->pluck('id');
                $quantiteTotale = 0;
                if ($ids->isNotEmpty() && Schema::hasTable('livraison_lignes')) {
                    $quantiteTotale = (int) DB::table('livraison_lignes')
                        ->whereIn('livraison_id', $ids)
                        ->sum('quantite');
                }
            }

            // Montant payé: somme des "montant_payer" dans livraison_lignes
            $montantTotalPaye = 0.0;
            $idsForMontant = (clone $base)->pluck('id');
            if ($idsForMontant->isNotEmpty() && Schema::hasTable('livraison_lignes') && Schema::hasColumn('livraison_lignes', 'montant_payer')) {
                $montantTotalPaye = (float) DB::table('livraison_lignes')
                    ->whereIn('livraison_id', $idsForMontant)
                    ->sum('montant_payer');
            }

            return $this->responseJson(true, 'Stats livraisons', [
                'range' => [
                    'from' => $from?->toDateString(),
                    'to'   => $to?->toDateString(),
                ],
                'livraisons' => [
                    'count'               => (int) $count,
                    'quantite_totale'     => (int) $quantiteTotale,
                    'montant_total_paye'  => (float) $montantTotalPaye,
                ],
            ]);
        } catch (ValidationException $e) {
            return $this->responseJson(false, 'Paramètres invalides.', ['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('[Stats/Livraisons] '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return $this->responseJson(false, 'Erreur lors du calcul des stats livraisons.', [
                'error' => config('app.debug') ? $e->getMessage() : 'internal_error'
            ], 500);
        }
    }

    private function resolveRange(array $v): array
    {
        $now = Carbon::now();

        if (!empty($v['periode'])) {
            return match ($v['periode']) {
                'aujourdhui'     => [$now->copy()->startOfDay(),   $now->copy()->endOfDay()],
                'cette_semaine'  => [$now->copy()->startOfWeek(),  $now->copy()->endOfWeek()],
                'ce_mois'        => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
                'cette_annee'    => [$now->copy()->startOfYear(),  $now->copy()->endOfYear()],
                default          => [null, null],
            };
        }

        $from = !empty($v['date_from']) ? Carbon::parse($v['date_from'])->startOfDay() : null;
        $to   = !empty($v['date_to'])   ? Carbon::parse($v['date_to'])->endOfDay()   : null;

        if (!$from && !$to) $to = $now->copy()->endOfDay();
        return [$from, $to];
    }
}
