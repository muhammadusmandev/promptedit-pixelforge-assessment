<?php

namespace App\Services;

use App\Models\Generation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AdminReportService
{
    /**
     * Generate admin financial report.
     */
    public function generate(array $filters): array
    {
        $startDate = $filters['start_date'];
        $endDate = $filters['end_date'];

        $perPage = $filters['per_page'] ?? 10;

        $baseQuery = Generation::query()
            ->whereBetween('completed_at', [
                $startDate . ' 00:00:00',
                $endDate . ' 23:59:59',
            ]);

        /*
         * Successful generations.
         */
        $successfulQuery = (clone $baseQuery)
            ->where('status', 'succeeded');

        /*
         * Financial summary.
         *
         * Financial calculations are performed by SQL
         * to avoid PHP floating-point arithmetic.
         */
        $summary = (clone $successfulQuery)
            ->selectRaw("
                COUNT(*) as successful_generations,
                COALESCE(SUM(actual_cost), 0) as user_charges,
                COALESCE(SUM(provider_cost), 0) as provider_costs,
                COALESCE(
                    SUM(
                        COALESCE(actual_cost, 0) -
                        COALESCE(provider_cost, 0)
                    ),
                    0
                ) as profit
            ")
            ->first();

        /*
         * Failed generation count.
         *
         * Failed generations may not have completed_at
         * if your webhook handler does not populate it.
         * Therefore, use created_at for failed records.
         */
        $failedCount = Generation::query()
            ->where('status', 'failed')
            ->whereBetween('created_at', [
                $startDate . ' 00:00:00',
                $endDate . ' 23:59:59',
            ])
            ->count();

        $rejectedCount = Generation::query()
            ->where('status', 'rejected')
            ->whereBetween('created_at', [
                $startDate . ' 00:00:00',
                $endDate . ' 23:59:59',
            ])
            ->count();

        /*
         * Daily financial breakdown.
         */
        $daily = (clone $successfulQuery)
            ->selectRaw("
                DATE(completed_at) as date,
                COUNT(*) as generations,
                COALESCE(SUM(actual_cost), 0) as user_charges,
                COALESCE(SUM(provider_cost), 0) as provider_costs,
                COALESCE(
                    SUM(
                        COALESCE(actual_cost, 0) -
                        COALESCE(provider_cost, 0)
                    ),
                    0
                ) as profit
            ")
            ->groupBy(DB::raw('DATE(completed_at)'))
            ->orderBy('date', 'desc')
            ->get()
            ->toArray();

        /*
         * Per-user financial breakdown.
         */
        $users = (clone $successfulQuery)
            ->join(
                'users',
                'generations.user_id',
                '=',
                'users.id'
            )
            ->selectRaw("
                users.id as user_id,
                users.name as user_name,
                users.email as email,
                COUNT(generations.id) as generations,
                COALESCE(
                    SUM(generations.actual_cost),
                    0
                ) as user_charges,
                COALESCE(
                    SUM(generations.provider_cost),
                    0
                ) as provider_costs,
                COALESCE(
                    SUM(
                        COALESCE(generations.actual_cost, 0) -
                        COALESCE(generations.provider_cost, 0)
                    ),
                    0
                ) as profit
            ")
            ->groupBy(
                'users.id',
                'users.name',
                'users.email'
            )
            ->orderByDesc('user_charges')
            ->paginate($perPage)
            ->withQueryString();

        return [
            'summary' => [
                'successful_generations' => (int) (
                    $summary->successful_generations ?? 0
                ),

                'failed_generations' => $failedCount,

                'total_generations' =>
                    (int) ($summary->successful_generations ?? 0)
                    + $failedCount,
                'rejected_generations' => $rejectedCount,
                'user_charges' => (string) (
                    $summary->user_charges ?? '0'
                ),

                'provider_costs' => (string) (
                    $summary->provider_costs ?? '0'
                ),

                'profit' => (string) (
                    $summary->profit ?? '0'
                ),
            ],

            'daily' => $daily,

            'users' => $users,
        ];
    }
}