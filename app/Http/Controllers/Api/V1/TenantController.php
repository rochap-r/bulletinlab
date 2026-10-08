<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Tenant\Contracts\TenantServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;

class TenantController extends Controller
{
    public function __construct(
        private TenantServiceInterface $tenantService
    ) {}

    public function index(): JsonResponse
    {
        $tenants = Tenant::all();

        return response()->json($tenants);
    }

    public function show(int $id): JsonResponse
    {
        $tenant = $this->tenantService->findById($id);

        return response()->json($tenant);
    }
}
