<?php

namespace App\Http\Middleware;

use App\Domains\Evaluation\Exceptions\PeriodLockedException;
use App\Domains\SchoolYear\Services\PeriodeLockService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PeriodeOuverteMiddleware
{
    /**
     * Handle an incoming request.
     *
     * Défense en profondeur : le contrôle applicatif de référence reste
     * LockMyCourseAssignmentNoteContextService (403/423 via ProblemDetails).
     * Ce middleware coupe au plus tôt les écritures sur période verrouillée
     * quand les identifiants classe + période sont présents.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $periodeId = $request->input('periode_id')
            ?? $request->input('periode')
            ?? $request->route('periode')
            ?? $request->route('periodeId');

        $classeId = $request->input('classe_id')
            ?? $request->input('classe')
            ?? $request->route('classe')
            ?? $request->route('classeId');

        if (! $periodeId || ! $classeId) {
            return $next($request);
        }

        $isLocked = app(PeriodeLockService::class)->isLocked((int) $periodeId, (int) $classeId);

        if ($isLocked) {
            throw new PeriodLockedException('Cette période est verrouillée. Impossible de modifier les notes.');
        }

        return $next($request);
    }
}
