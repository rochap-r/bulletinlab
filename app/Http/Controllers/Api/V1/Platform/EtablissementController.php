<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Platform\PlatformEtablissementRequest;
use App\Http\Resources\Api\V1\Platform\EtablissementResource;
use App\Models\Etablissement;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;

final class EtablissementController extends Controller
{
    public function index(int $tenant): JsonResponse
    {
        $tenantModel = Tenant::query()->findOrFail($tenant);

        $etablissements = Etablissement::query()
            ->where('tenant_id', $tenantModel->id)
            ->orderBy('nom')
            ->get();

        return EtablissementResource::collection($etablissements)->response();
    }

    public function store(PlatformEtablissementRequest $request, int $tenant): JsonResponse
    {
        $tenantModel = Tenant::query()->findOrFail($tenant);

        $etablissement = Etablissement::query()->create([
            ...$request->validated(),
            'tenant_id' => $tenantModel->id,
            'actif' => $request->validated('actif') ?? true,
        ]);

        return EtablissementResource::make($etablissement)
            ->response()
            ->setStatusCode(201);
    }

    public function update(PlatformEtablissementRequest $request, int $etablissement): JsonResponse
    {
        $etablissementModel = Etablissement::query()->findOrFail($etablissement);
        $etablissementModel->update($request->validated());

        return EtablissementResource::make($etablissementModel->refresh())->response();
    }
}
