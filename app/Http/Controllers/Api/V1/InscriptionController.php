<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Student\Services\EnrollmentService;
use App\Domains\Student\Services\ExitEnrollmentService;
use App\Domains\Tenant\Contexts\EstablishmentContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PaginationRequest;
use App\Http\Requests\Api\V1\Student\ExitEnrollmentRequest;
use App\Http\Requests\Api\V1\Student\InscriptionRequest;
use App\Http\Resources\Api\V1\InscriptionResource;
use App\Models\Classe;
use App\Models\Inscription;
use Illuminate\Http\JsonResponse;

final class InscriptionController extends Controller
{
    public function __construct(
        private EnrollmentService $enrollmentService,
        private ExitEnrollmentService $exitEnrollmentService,
        private EstablishmentContext $establishmentContext,
    ) {}

    public function index(PaginationRequest $request, int $classe): JsonResponse
    {
        $resolvedClass = $this->resolveClass($classe);
        $enrollments = Inscription::query()
            ->where('classe_id', $resolvedClass->id)
            ->with('eleve')
            ->latest('id')
            ->paginate($request->integer('per_page', 50));

        return InscriptionResource::collection($enrollments)->response();
    }

    public function store(InscriptionRequest $request, int $classe): JsonResponse
    {
        $resolvedClass = $this->resolveClass($classe);
        $enrollment = $this->enrollmentService->enroll(
            $resolvedClass,
            $request->enrollmentData(),
        );

        return InscriptionResource::make($enrollment)
            ->response()
            ->setStatusCode(201);
    }

    public function show(int $inscription): JsonResponse
    {
        $enrollment = Inscription::query()
            ->whereHas(
                'classe',
                fn ($query) => $query->where(
                    'etablissement_id',
                    $this->establishmentContext->id(),
                ),
            )
            ->with('eleve')
            ->findOrFail($inscription);

        return InscriptionResource::make($enrollment)->response();
    }

    public function recordExit(ExitEnrollmentRequest $request): JsonResponse
    {
        $enrollment = $this->exitEnrollmentService->recordExit(
            $request->enrollment(),
            $request->actor(),
            $request->exitData(),
        );

        return InscriptionResource::make($enrollment)->response();
    }

    private function resolveClass(int $classId): Classe
    {
        return Classe::query()
            ->where('etablissement_id', $this->establishmentContext->id())
            ->findOrFail($classId);
    }
}
