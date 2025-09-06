<?php

namespace App\Http\Controllers\Dashboard;


use App\Http\Controllers\Controller;
use App\Models\Encaissement;
use App\Traits\JsonResponseTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class StatistiqueEncaissementController extends Controller
{
    use JsonResponseTrait;

    /**
     * GET /api/dashboards/statistiques/encaissements
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

            // si la table n’existe pas, on renvoie 0 partout
            $table = (new Encaissement())->getTable();
            if (!Schema::hasTable($table)) {
                return $this->responseJson(true, 'Stats encaissements', [
                    'range' => ['from' => $from?->toDateString(), 'to' => $to?->toDateString()],
                    'encaissements' => ['cash' => 0, 'orange_money' => 0, 'depot_banque' => 0, 'total' => 0],
                ]);
            }

            // Colonne de date tolérée: date_encaissement | date | created_at
            $dateCol = Schema::hasColumn($table, 'date_encaissement')
                ? 'date_encaissement'
                : (Schema::hasColumn($table, 'date') ? 'date' : 'created_at');

            $base = Encaissement::query();
            if ($from) $base->whereDate($dateCol, '>=', $from);
            if ($to)   $base->whereDate($dateCol, '<=', $to);

            // valeurs tolérées par mode_paiement
            $CASH  = ['espèces', 'especes', 'cash', 'espece'];
            $OM    = ['orange-money', 'orange_money', 'om', 'orange money'];
            $DEPOT = ['dépot-banque', 'depot-banque', 'depot_banque', 'depot banque', 'banque'];

            $cash   = (clone $base)->whereIn('mode_paiement', $CASH)->sum('montant');
            $orange = (clone $base)->whereIn('mode_paiement', $OM)->sum('montant');
            $depot  = (clone $base)->whereIn('mode_paiement', $DEPOT)->sum('montant');
            $total  = (clone $base)->sum('montant');

            return $this->responseJson(true, 'Stats encaissements', [
                'range' => [
                    'from' => $from?->toDateString(),
                    'to'   => $to?->toDateString(),
                ],
                'encaissements' => [
                    'cash'          => (float) $cash,
                    'orange_money'  => (float) $orange,
                    'depot_banque'  => (float) $depot,
                    'total'         => (float) $total,
                ],
            ]);
        } catch (ValidationException $e) {
            return $this->responseJson(false, 'Paramètres invalides.', ['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('[Stats/Encaissements] '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return $this->responseJson(false, 'Erreur lors du calcul des encaissements.', [
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
