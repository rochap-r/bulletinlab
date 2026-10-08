<?php

namespace App\Http\Controllers\Api\V1\Onboarding;

use App\Domains\Tenant\Contracts\TenantRegistrationServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Onboarding\RegisterTenantRequest;
use App\Http\Resources\Api\V1\Onboarding\RegisteredTenantResource;
use Illuminate\Http\JsonResponse;

final class RegisterTenantController extends Controller
{
    public function __construct(
        private readonly TenantRegistrationServiceInterface $tenantRegistrationService,
    ) {}

    public function __invoke(RegisterTenantRequest $request): JsonResponse
    {
        $result = $this->tenantRegistrationService->register(
            $request->registrationData(),
        );

        return RegisteredTenantResource::make($result)
            ->response()
            ->setStatusCode(201);
    }
}
