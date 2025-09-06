<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\JsonResponseTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class StatistiqueUserController extends Controller
{
    use JsonResponseTrait;

    /**
     * GET /api/dashboards/statistiques/users
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

            // Sécurité : table users existante ?
            $userTable = (new User())->getTable(); // "users"
            if (!Schema::hasTable($userTable)) {
                return $this->emptyResponse($from, $to);
            }

            // Colonne date (création)
            $dateCol = Schema::hasColumn($userTable, 'created_at') ? 'created_at' : 'created_at';

            // Base filtrée par période (utilisateurs créés dans la période)
            $base = User::query();
            if ($from) $base->whereDate($dateCol, '>=', $from);
            if ($to)   $base->whereDate($dateCol, '<=', $to);

            // Totaux
            $countAll  = (clone $base)->count();
            $hasEmailVerifiedAt = Schema::hasColumn($userTable, 'email_verified_at');

            $countVerified   = $hasEmailVerifiedAt
                ? (clone $base)->whereNotNull('email_verified_at')->count()
                : 0;
            $countUnverified = $countAll - $countVerified;

            // Répartition par rôle (Spatie) : roles + model_has_roles + users (filtré par created_at)
            $rolesData = [];
            if (Schema::hasTable('roles') && Schema::hasTable('model_has_roles')) {
                $qRole = DB::table('model_has_roles as mhr')
                    ->join('roles as r', 'r.id', '=', 'mhr.role_id')
                    ->join($userTable.' as u', 'u.id', '=', 'mhr.model_id')
                    ->where('mhr.model_type', User::class);

                if ($from) $qRole->whereDate('u.'.$dateCol, '>=', $from);
                if ($to)   $qRole->whereDate('u.'.$dateCol, '<=', $to);

                $rolesData = $qRole
                    ->select('r.name as role', DB::raw('COUNT(*) as count'))
                    ->groupBy('r.name')
                    ->orderBy('count', 'desc')
                    ->get()
                    ->map(fn($row) => ['role' => $row->role, 'count' => (int)$row->count])
                    ->toArray();
            }

            // Répartition par agence (si colonne users.agence_id et table agences existent)
            $agencesData = [];
            $hasAgenceId = Schema::hasColumn($userTable, 'agence_id');
            if ($hasAgenceId) {
                $qAgence = DB::table($userTable.' as u');
                if ($from) $qAgence->whereDate('u.'.$dateCol, '>=', $from);
                if ($to)   $qAgence->whereDate('u.'.$dateCol, '<=', $to);

                if (Schema::hasTable('agences')) {
                    $qAgence->leftJoin('agences as a', 'a.id', '=', 'u.agence_id')
                            ->select('u.agence_id', 'a.nom as agence_nom', DB::raw('COUNT(*) as count'))
                            ->groupBy('u.agence_id', 'a.nom');
                } else {
                    $qAgence->select('u.agence_id', DB::raw('COUNT(*) as count'))
                            ->groupBy('u.agence_id');
                }

                $agencesData = $qAgence->get()->map(function ($row) {
                    return [
                        'agence_id' => $row->agence_id,
                        'agence'    => property_exists($row, 'agence_nom') ? ($row->agence_nom ?? '—') : null,
                        'count'     => (int)$row->count,
                    ];
                })->toArray();
            }

            return $this->responseJson(true, 'Stats users', [
                'range' => [
                    'from' => $from?->toDateString(),
                    'to'   => $to?->toDateString(),
                ],
                'users' => [
                    'count'        => (int) $countAll,
                    'verified'     => (int) $countVerified,
                    'unverified'   => (int) $countUnverified,
                    'par_role'     => $rolesData,   // ex: [ { role: "admin", count: 3 }, ... ]
                    'par_agence'   => $agencesData, // ex: [ { agence_id: 1, agence: "Siège", count: 5 }, ... ]
                ],
            ]);
        } catch (ValidationException $e) {
            return $this->responseJson(false, 'Paramètres invalides.', ['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('[Stats/Users] '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return $this->responseJson(false, 'Erreur lors du calcul des stats users.', [
                'error' => config('app.debug') ? $e->getMessage() : 'internal_error'
            ], 500);
        }
    }

    private function emptyResponse(?Carbon $from, ?Carbon $to)
    {
        return $this->responseJson(true, 'Stats users', [
            'range' => ['from' => $from?->toDateString(), 'to' => $to?->toDateString()],
            'users' => [
                'count'      => 0,
                'verified'   => 0,
                'unverified' => 0,
                'par_role'   => [],
                'par_agence' => [],
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
