<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Socle de traçabilité des requêtes API.
 *
 * - Attribue un `request_id` (UUID) propagé en header `X-Request-Id`
 *   et dans chaque ligne de log via le contexte partagé.
 * - Journalise une ligne par requête `api/*` sur le canal `http`
 *   (méthode, chemin, statut, durée, tenant, utilisateur).
 * - Ne journalise jamais les corps de requête (mots de passe, tokens).
 */
final class RequestLogging
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = (string) Str::uuid();
        $request->attributes->set('request_id', $requestId);

        Context::add([
            'request_id' => $requestId,
            'ip' => $request->ip(),
            'method' => $request->method(),
            'path' => $request->path(),
        ]);

        $startedAt = microtime(true);

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $this->log($request, null, microtime(true) - $startedAt, $exception);

            throw $exception;
        }

        $response->headers->set('X-Request-Id', $requestId);
        $this->log($request, $response->getStatusCode(), microtime(true) - $startedAt);

        return $response;
    }

    private function log(Request $request, ?int $status, float $duration, ?Throwable $exception = null): void
    {
        if (! $request->is('api/*')) {
            return;
        }

        $context = [
            'method' => $request->method(),
            'path' => $request->path(),
            'status' => $status,
            'duration_ms' => (int) round($duration * 1000),
            'tenant_id' => Context::get('tenant_id'),
            'user_id' => Context::get('user_id'),
            'user_type' => Context::get('user_type'),
        ];

        if ($exception !== null) {
            $context['error'] = $exception::class;

            Log::channel('http')->error('api.request', $context);

            return;
        }

        if ($status !== null && $status >= 500) {
            Log::channel('http')->error('api.request', $context);

            return;
        }

        Log::channel('http')->info('api.request', $context);
    }
}
