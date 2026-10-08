<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Domains\Tenant\Contracts\TenantValidationServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Platform\PlatformTenantRejectionRequest;
use App\Http\Requests\Api\V1\Platform\PlatformTenantRequest;
use App\Http\Resources\Api\V1\Platform\TenantResource;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class TenantController extends Controller
{
    public function __construct(
        private readonly TenantValidationServiceInterface $tenantValidationService,
    ) {}

    public function index(): JsonResponse
    {
        $tenants = Tenant::query()
            ->withCount('etablissements')
            ->orderBy('nom_espace')
            ->get();

        return TenantResource::collection($tenants)->response();
    }

    public function store(PlatformTenantRequest $request): JsonResponse
    {
        $data = $request->validated();

        $tenant = Tenant::query()->create([
            'uuid' => (string) Str::uuid(),
            'nom_espace' => $data['nom_espace'],
            'slug' => $this->uniqueSlug($data['nom_espace']),
            'type_tenant' => $data['type_tenant'],
            'statut' => $data['statut'] ?? 'ESSAI',
        ]);

        return TenantResource::make($tenant->loadCount('etablissements'))
            ->response()
            ->setStatusCode(201);
    }

    public function show(int $tenant): JsonResponse
    {
        $tenantModel = Tenant::query()
            ->withCount('etablissements')
            ->findOrFail($tenant);

        $tenantModel->load('etablissements');

        return TenantResource::make($tenantModel)->response();
    }

    public function update(PlatformTenantRequest $request, int $tenant): JsonResponse
    {
        $tenantModel = Tenant::query()->findOrFail($tenant);
        $data = $request->validated();

        $tenantModel->update([
            'nom_espace' => $data['nom_espace'],
            'type_tenant' => $data['type_tenant'],
            'statut' => $data['statut'] ?? $tenantModel->statut,
        ]);

        return TenantResource::make($tenantModel->refresh()->loadCount('etablissements'))
            ->response();
    }

    public function activate(Request $request, int $tenant): JsonResponse
    {
        $tenantModel = Tenant::query()->findOrFail($tenant);

        $validated = $this->tenantValidationService->activate(
            $tenantModel,
            $request->user(),
        );

        return TenantResource::make($validated->loadCount('etablissements'))
            ->response();
    }

    public function reject(PlatformTenantRejectionRequest $request, int $tenant): JsonResponse
    {
        $tenantModel = Tenant::query()->findOrFail($tenant);

        $refused = $this->tenantValidationService->reject(
            $tenantModel,
            $request->user(),
            $request->motif(),
        );

        return TenantResource::make($refused->loadCount('etablissements'))
            ->response();
    }

    private function uniqueSlug(string $nom): string
    {
        $base = Str::slug($nom);
        $slug = $base;
        $suffix = 1;

        while (Tenant::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
