<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domains\Auth\Contracts\AuthServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\SwitchTenantRequest;
use App\Http\Resources\Api\V1\Auth\AuthenticatedSessionResource;
use App\Models\Utilisateur;

final class SelectTenantController extends Controller
{
    public function __construct(private readonly AuthServiceInterface $authService) {}

    public function __invoke(SwitchTenantRequest $request): AuthenticatedSessionResource
    {
        /** @var Utilisateur $selector */
        $selector = $request->user();

        return new AuthenticatedSessionResource(
            $this->authService->selectTenant($selector, (int) $request->integer('tenant_id')),
        );
    }
}
