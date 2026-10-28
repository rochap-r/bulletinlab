<?php

namespace App\Domains\Responsable\Services;

use App\Models\Responsable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class ResponsableMonitoringService
{
    private const METRICS = [
        'auth_success',
        'auth_failure',
        'children_read',
        'evaluations_read',
        'read_failure',
        'read_duration_ms_total',
    ];

    public function recordAuthentication(bool $successful, ?Responsable $responsable = null): void
    {
        $this->increment($successful ? 'auth_success' : 'auth_failure');

        if (! $successful || ! $responsable) {
            return;
        }

        $summary = DB::table('lien_responsable_eleves')
            ->where('responsable_id', $responsable->getKey())
            ->where('statut', 'VERIFIE')
            ->whereNull('revoque_at')
            ->selectRaw('COUNT(*) as children_count, COUNT(DISTINCT tenant_id) as schools_count')
            ->first();

        $childrenCount = (int) ($summary->children_count ?? 0);
        $schoolsCount = (int) ($summary->schools_count ?? 0);
        if ($childrenCount >= (int) config('responsables.alerts.max_children', 50)
            || $schoolsCount >= (int) config('responsables.alerts.max_schools', 10)) {
            Log::channel('security')->warning('responsable.links.threshold_exceeded', [
                'responsable_public_id' => $responsable->public_id,
                'children_count' => $childrenCount,
                'schools_count' => $schoolsCount,
            ]);
        }
    }

    public function recordRead(string $resource, float $durationMilliseconds, bool $successful = true): void
    {
        $metric = $resource === 'evaluations' ? 'evaluations_read' : 'children_read';
        $this->increment($successful ? $metric : 'read_failure');

        if ($successful) {
            $this->increment('read_duration_ms_total', max(0, (int) round($durationMilliseconds)));
        }

        Log::info('responsable.portal.read', [
            'resource' => $resource,
            'successful' => $successful,
            'duration_ms' => round($durationMilliseconds, 2),
        ]);
    }

    /** @return array<int, array{date: string, metrics: array<string, int>, average_read_duration_ms: float}> */
    public function snapshot(int $days = 7): array
    {
        return collect(range(0, max(0, $days - 1)))
            ->map(function (int $daysAgo): array {
                $date = now()->subDays($daysAgo)->toDateString();
                $metrics = collect(self::METRICS)
                    ->mapWithKeys(fn (string $metric): array => [
                        $metric => (int) Cache::get($this->key($date, $metric), 0),
                    ])
                    ->all();
                $reads = $metrics['children_read'] + $metrics['evaluations_read'];

                return [
                    'date' => $date,
                    'metrics' => $metrics,
                    'average_read_duration_ms' => $reads > 0
                        ? round($metrics['read_duration_ms_total'] / $reads, 2)
                        : 0.0,
                ];
            })
            ->all();
    }

    private function increment(string $metric, int $value = 1): void
    {
        $key = $this->key(now()->toDateString(), $metric);
        Cache::add($key, 0, now()->addDays(8));
        Cache::increment($key, $value);
    }

    private function key(string $date, string $metric): string
    {
        return "responsable:metrics:{$date}:{$metric}";
    }
}
