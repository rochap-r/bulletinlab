<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Student\Services\ArchiveStudentService;
use App\Domains\Student\Services\ReactivateStudentService;
use App\Domains\Tenant\Contexts\EstablishmentContext;
use App\Domains\Tenant\Contexts\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PaginationRequest;
use App\Http\Requests\Api\V1\Student\ArchiveStudentRequest;
use App\Http\Requests\Api\V1\Student\EleveRequest;
use App\Http\Requests\Api\V1\Student\ReactivateStudentRequest;
use App\Http\Resources\Api\V1\EleveResource;
use App\Models\Eleve;
use Illuminate\Http\JsonResponse;

final class EleveController extends Controller
{
    public function __construct(
        private TenantContext $tenantContext,
        private EstablishmentContext $establishmentContext,
        private ArchiveStudentService $archiveStudentService,
        private ReactivateStudentService $reactivateStudentService,
    ) {}

    public function index(PaginationRequest $request): JsonResponse
    {
        $students = Eleve::query()
            ->where('etablissement_id', $this->establishmentContext->id())
            ->latest('id')
            ->paginate($request->integer('per_page', 50));

        return EleveResource::collection($students)->response();
    }

    public function store(EleveRequest $request): JsonResponse
    {
        $student = Eleve::query()->create([
            ...$request->validated(),
            'tenant_id' => $this->tenantContext->id(),
            'etablissement_id' => $this->establishmentContext->id(),
        ]);

        return EleveResource::make($student)
            ->response()
            ->setStatusCode(201);
    }

    public function show(int $eleve): JsonResponse
    {
        return EleveResource::make($this->resolveStudent($eleve))->response();
    }

    public function update(EleveRequest $request, int $eleve): JsonResponse
    {
        $student = $this->resolveStudent($eleve);
        $student->update($request->validated());

        return EleveResource::make($student->refresh())->response();
    }

    public function archive(ArchiveStudentRequest $request): JsonResponse
    {
        $student = $this->archiveStudentService->archive(
            $request->student(),
            $request->actor(),
            $request->archiveData(),
        );

        return EleveResource::make($student)->response();
    }

    public function reactivate(ReactivateStudentRequest $request): JsonResponse
    {
        $student = $this->reactivateStudentService->reactivate(
            $request->student(),
            $request->actor(),
            $request->reactivateData(),
        );

        return EleveResource::make($student)->response();
    }

    private function resolveStudent(int $studentId): Eleve
    {
        return Eleve::query()
            ->where('etablissement_id', $this->establishmentContext->id())
            ->findOrFail($studentId);
    }
}
