<?php

namespace App\Domains\Tenant\Contracts;

use Illuminate\Database\Eloquent\Model;

interface IdempotencyServiceInterface
{
    /**
     * Exécute $callback exactement une fois pour un triplet
     * (contexte, acteur, commande + clé).
     *
     * Si la même clé a déjà été traitée avec le même hash de requête, le
     * résultat initial est retourné. Si le hash diffère, une
     * {@see IdempotencyConflictException} est levée.
     *
     * @template TResult of mixed
     *
     * @param  callable(): TResult  $callback
     * @return TResult
     */
    public function execute(
        string $key,
        string $commandName,
        callable $callback,
        ?Model $context = null,
        ?Model $actor = null,
        ?string $requestHash = null,
    ): mixed;

    /**
     * Hash canonique d'un payload normalisé.
     *
     * @param  array<string, mixed>  $payload
     */
    public function hashPayload(array $payload): string;
}
