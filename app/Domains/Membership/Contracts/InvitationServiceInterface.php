<?php

namespace App\Domains\Membership\Contracts;

use App\Domains\Membership\DTOs\AcceptanceData;
use App\Domains\Membership\DTOs\CreatedInvitationResult;
use App\Domains\Membership\DTOs\CreateInvitationData;
use App\Models\Etablissement;
use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\Utilisateur;

interface InvitationServiceInterface
{
    public function create(
        CreateInvitationData $data,
        Tenant $tenant,
        Etablissement $establishment,
        Utilisateur $actor,
    ): CreatedInvitationResult;

    public function revoke(Invitation $invitation, Utilisateur $actor): Invitation;

    public function accept(AcceptanceData $data): Utilisateur;
}
