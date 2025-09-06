<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\FactureLivraison;
use App\Traits\JsonResponseTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StatistiqueFactureController extends Controller
{
    use JsonResponseTrait;

    /**
     * GET /api/dashboards/statistiques/factures
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

            $table = (new FactureLivraison())->getTable();
            if (!Schema::hasTable($table)) {
                return $this->emptyResponse($from, $to);
            }

            // Colonnes dynamiques
            $dateCol   = $this->pickFirstExisting($table, ['date_facture','date','created_at']) ?? 'created_at';
            $totalCol  = $this->pickFirstExisting($table, ['total_ttc','montant_ttc','total']);
            $hasDuCol  = Schema::hasColumn($table, 'montant_du');

            $base = FactureLivraison::query();
            if ($from) $base->whereDate($dateCol, '>=', $from);
            if ($to)   $base->whereDate($dateCol, '<=', $to);

            // Globaux
            $countAll  = (clone $base)->count();
            $sumAllTtc = $totalCol ? (float) (clone $base)->sum($totalCol) : 0.0;
            $sumDu     = $hasDuCol ? (float) (clone $base)->sum('montant_du') : 0.0;

            // Agrégat brut par statut
            $rows = (clone $base)
                ->when($totalCol, fn($q) =>
                    $q->selectRaw('statut, COUNT(*) as c, SUM('.$totalCol.') as s'),
                    fn($q) => $q->selectRaw('statut, COUNT(*) as c, 0 as s')
                )
                ->groupBy('statut')
                ->get();

            // Sortie alignée sur les constantes du modèle
            $BROUILLON = FactureLivraison::STATUT_BROUILLON; // 'brouillon'
            $PARTIEL   = FactureLivraison::STATUT_PARTIEL;   // 'partiel'
            $PAYE      = FactureLivraison::STATUT_PAYE;      // 'payé'
            $IMPAYE    = FactureLivraison::STATUT_IMPAYE;    // 'impayé'

            $parStatut = [
                $BROUILLON => ['count' => 0, 'total_ttc' => 0.0],
                $PARTIEL   => ['count' => 0, 'total_ttc' => 0.0],
                $PAYE      => ['count' => 0, 'total_ttc' => 0.0],
                $IMPAYE    => ['count' => 0, 'total_ttc' => 0.0],
            ];

            foreach ($rows as $row) {
                $raw  = (string) ($row->statut ?? '');
                $norm = $this->normalizeKey($raw); // ascii + lower + _

                // Map des variantes -> constantes modèle
                $key = match (true) {
                    $norm === 'brouillon'                                     => $BROUILLON,
                    $norm === 'partiel' || str_contains($norm, 'partiel')     => $PARTIEL,
                    in_array($norm, ['paye','payee','paye_e','payee_e','paye_s','payee_s','payees','payes']) => $PAYE,
                    in_array($norm, ['impaye','impayee','impaye_e','impayee_e','impayes','impayees']) => $IMPAYE,
                    // Heuristiques (sécurité)
                    str_contains($norm, 'pay') && !str_contains($norm, 'im')  => $PAYE,
                    str_contains($norm, 'impay')                              => $IMPAYE,
                    default                                                   => $IMPAYE, // fallback côté modèle
                };

                $parStatut[$key]['count']     += (int) $row->c;
                $parStatut[$key]['total_ttc'] += (float) $row->s;
            }

            return $this->responseJson(true, 'Stats factures', [
                'range' => [
                    'from' => $from?->toDateString(),
                    'to'   => $to?->toDateString(),
                ],
                'factures' => [
                    'count'            => (int) $countAll,
                    'total_ttc'        => (float) $sumAllTtc,
                    'montant_du_total' => (float) $sumDu,
                    // 👇 même principe que "commandes.par_statut"
                    'par_statut'       => $parStatut,
                ],
            ]);

        } catch (ValidationException $e) {
            return $this->responseJson(false, 'Paramètres invalides.', ['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('[Stats/Factures] '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return $this->responseJson(false, 'Erreur lors du calcul des stats de factures.', [
                'error' => config('app.debug') ? $e->getMessage() : 'internal_error'
            ], 500);
        }
    }

    private function emptyResponse(?Carbon $from, ?Carbon $to)
    {
        $BROUILLON = FactureLivraison::STATUT_BROUILLON;
        $PARTIEL   = FactureLivraison::STATUT_PARTIEL;
        $PAYE      = FactureLivraison::STATUT_PAYE;
        $IMPAYE    = FactureLivraison::STATUT_IMPAYE;

        return $this->responseJson(true, 'Stats factures', [
            'range' => ['from' => $from?->toDateString(), 'to' => $to?->toDateString()],
            'factures' => [
                'count'            => 0,
                'total_ttc'        => 0,
                'montant_du_total' => 0,
                'par_statut'       => [
                    $BROUILLON => ['count' => 0, 'total_ttc' => 0],
                    $PARTIEL   => ['count' => 0, 'total_ttc' => 0],
                    $PAYE      => ['count' => 0, 'total_ttc' => 0],
                    $IMPAYE    => ['count' => 0, 'total_ttc' => 0],
                ],
            ],
        ]);
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

    /** ascii + lower + remplace espaces/traits par "_" */
    private function normalizeKey(?string $key): string
    {
        $k = Str::of((string) $key)->ascii()->lower()->value();
        $k = preg_replace('/[\s\-]+/u', '_', $k);
        $k = preg_replace('/_+/u', '_', $k);
        return trim($k ?? '');
    }

    private function pickFirstExisting(string $table, array $candidates): ?string
    {
        foreach ($candidates as $col) {
            if (Schema::hasColumn($table, $col)) return $col;
        }
        return null;
    }
}
