<?php

namespace App\Domains\Membership\DTOs;

final readonly class CreateInvitationData
{
    public function __construct(
        public string $email,
        public int $roleId,
    ) {}
}
