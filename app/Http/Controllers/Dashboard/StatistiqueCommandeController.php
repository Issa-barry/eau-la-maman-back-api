<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Commande;
use App\Traits\JsonResponseTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class StatistiqueCommandeController extends Controller
{
    use JsonResponseTrait;

    /**
     * GET /api/dashboards/statistiques/commandes
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

            // Sécurité : table/colonnes
            $table = (new Commande())->getTable(); // commandes
            if (!Schema::hasTable($table)) {
                return $this->emptyResponse($from, $to);
            }

            // Colonne date (fallback si besoin)
            $dateCol = Schema::hasColumn($table, 'created_at')
                ? 'created_at'
                : (Schema::hasColumn($table, 'date') ? 'date' : 'created_at');

            $amountCol = Schema::hasColumn($table, 'montant_total') ? 'montant_total' : null;

            $base = Commande::query();
            if ($from) $base->whereDate($dateCol, '>=', $from);
            if ($to)   $base->whereDate($dateCol, '<=', $to);

            // Variantes de statuts tolérées
            $BROUILLON  = ['brouillon'];
            $EN_LIVRAISON = ['livraison_en_cours'];
            $LIVRE      = ['livré', 'livre'];
            $CLOTURE    = ['cloturé', 'cloture', 'clôturé', 'clôture'];
            $ANNULE     = ['annulé', 'annule'];

            // Compteurs globaux
            $countAll = (clone $base)->count();
            $sumAll   = $amountCol ? (float) (clone $base)->sum($amountCol) : 0.0;

            // Par statut
            $cBrouillon = (clone $base)->whereIn('statut', $BROUILLON)->count();
            $sBrouillon = $amountCol ? (float) (clone $base)->whereIn('statut', $BROUILLON)->sum($amountCol) : 0.0;

            $cEnLiv    = (clone $base)->whereIn('statut', $EN_LIVRAISON)->count();
            $sEnLiv    = $amountCol ? (float) (clone $base)->whereIn('statut', $EN_LIVRAISON)->sum($amountCol) : 0.0;

            $cLivre    = (clone $base)->whereIn('statut', $LIVRE)->count();
            $sLivre    = $amountCol ? (float) (clone $base)->whereIn('statut', $LIVRE)->sum($amountCol) : 0.0;

            $cCloture  = (clone $base)->whereIn('statut', $CLOTURE)->count();
            $sCloture  = $amountCol ? (float) (clone $base)->whereIn('statut', $CLOTURE)->sum($amountCol) : 0.0;

            $cAnnule   = (clone $base)->whereIn('statut', $ANNULE)->count();
            $sAnnule   = $amountCol ? (float) (clone $base)->whereIn('statut', $ANNULE)->sum($amountCol) : 0.0;

            return $this->responseJson(true, 'Stats commandes', [
                'range' => [
                    'from' => $from?->toDateString(),
                    'to'   => $to?->toDateString(),
                ],
                'commandes' => [
                    'count'         => (int) $countAll,
                    'total'         => (float) $sumAll,            // somme montant_total (si présent)
                    'par_statut'    => [
                        'brouillon'           => ['count' => (int) $cBrouillon, 'total' => (float) $sBrouillon],
                        'livraison_en_cours'  => ['count' => (int) $cEnLiv,    'total' => (float) $sEnLiv],
                        'livre'               => ['count' => (int) $cLivre,    'total' => (float) $sLivre],
                        'cloture'             => ['count' => (int) $cCloture,  'total' => (float) $sCloture],
                        'annule'              => ['count' => (int) $cAnnule,   'total' => (float) $sAnnule],
                    ],
                ],
            ]);
        } catch (ValidationException $e) {
            return $this->responseJson(false, 'Paramètres invalides.', ['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('[Stats/Commandes] '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return $this->responseJson(false, 'Erreur lors du calcul des stats commandes.', [
                'error' => config('app.debug') ? $e->getMessage() : 'internal_error'
            ], 500);
        }
    }

    private function emptyResponse(?Carbon $from, ?Carbon $to)
    {
        return $this->responseJson(true, 'Stats commandes', [
            'range' => ['from' => $from?->toDateString(), 'to' => $to?->toDateString()],
            'commandes' => [
                'count' => 0,
                'total' => 0,
                'par_statut' => [
                    'brouillon' => ['count' => 0, 'total' => 0],
                    'livraison_en_cours' => ['count' => 0, 'total' => 0],
                    'livre' => ['count' => 0, 'total' => 0],
                    'cloture' => ['count' => 0, 'total' => 0],
                    'annule' => ['count' => 0, 'total' => 0],
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
}
