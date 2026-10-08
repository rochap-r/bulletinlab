<?php

namespace App\Domains\Membership\DTOs;

use App\Models\Invitation;

final readonly class CreatedInvitationResult
{
    public function __construct(
        public Invitation $invitation,
        public string $token,
    ) {}
}
