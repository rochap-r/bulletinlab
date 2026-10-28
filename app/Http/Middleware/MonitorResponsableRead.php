<?php

namespace App\Http\Middleware;

use App\Domains\Responsable\Services\ResponsableMonitoringService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final readonly class MonitorResponsableRead
{
    public function __construct(
        private ResponsableMonitoringService $monitoringService,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = hrtime(true);
        $resource = $request->routeIs('api.v1.responsables.children.evaluations')
            ? 'evaluations'
            : 'children';

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $this->monitoringService->recordRead(
                $resource,
                (hrtime(true) - $startedAt) / 1_000_000,
                false,
            );

            throw $exception;
        }

        $this->monitoringService->recordRead(
            $resource,
            (hrtime(true) - $startedAt) / 1_000_000,
            $response->isSuccessful(),
        );

        return $response;
    }
}
