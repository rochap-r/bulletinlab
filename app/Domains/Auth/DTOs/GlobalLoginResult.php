<?php

namespace App\Domains\Auth\DTOs;

final readonly class GlobalLoginResult
{
    /**
     * @param  array<int, array{tenant_id: int, slug: string, nom_espace: string, roles: array<int, string>, permissions: array<int, string>}>  $memberships
     */
    public function __construct(
        public ?AuthenticatedSession $session,
        public array $memberships,
        public ?string $selectToken,
    ) {}
}
