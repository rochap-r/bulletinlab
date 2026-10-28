<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

final class RejectClientManagedContext
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $errors = [];

        foreach (['tenant_id', 'etablissement_id'] as $attribute) {
            if ($request->exists($attribute)) {
                $errors[$attribute] = ["The {$attribute} field is managed by the server."];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $next($request);
    }
}
