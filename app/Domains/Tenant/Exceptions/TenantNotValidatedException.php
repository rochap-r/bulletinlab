<?php

namespace App\Domains\Tenant\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;

/**
 * Lever à la place d'un accès HTTP 403 tant que le compte tenant reste
 * en attente de validation par la plateforme.
 */
final class TenantNotValidatedException extends AuthorizationException
{
    //
}
