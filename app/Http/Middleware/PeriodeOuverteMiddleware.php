<?php

namespace App\Http\Middleware;

use App\Domains\SchoolYear\Services\PeriodeLockService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PeriodeOuverteMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $periodeId = $request->input('periode_id') ?? $request->route('periodeId');
        $classeId = $request->input('classe_id') ?? $request->route('classeId');

        if (!$periodeId || !$classeId) {
            return $next($request);
        }

        $isLocked = app(PeriodeLockService::class)->isLocked($periodeId, $classeId);

        if ($isLocked) {
            return response()->json([
                'message' => 'Cette période est verrouillée. Impossible de modifier les notes.',
            ], 403);
        }

        return $next($request);
    }
}
