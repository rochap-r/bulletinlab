<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\SchoolYear\Services\AttendanceService;
use App\Domains\Tenant\Contexts\EstablishmentContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Attendance\AttendanceIndexRequest;
use App\Http\Requests\Api\V1\Attendance\StoreAttendanceRequest;
use App\Http\Requests\Api\V1\Attendance\StudentAttendanceIndexRequest;
use App\Http\Resources\Api\V1\AttendanceResource;
use App\Models\Classe;
use App\Models\Inscription;
use App\Models\ModelePeriode;
use App\Models\Utilisateur;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;

final class AttendanceController extends Controller
{
    public function __construct(
        private AttendanceService $attendanceService,
        private EstablishmentContext $establishmentContext,
    ) {}

    public function store(
        StoreAttendanceRequest $request,
        int $classe,
    ): JsonResponse {
        $resolvedClass = $this->resolveClass($classe);
        $validated = $request->validated();
        $inscription = Inscription::query()
            ->where('classe_id', $resolvedClass->id)
            ->with(['classe', 'eleve'])
            ->findOrFail($validated['inscription_id']);

        $this->ensurePeriodBelongsToClass(
            $resolvedClass,
            $validated['periode_id'] ?? null,
        );

        $attendance = $this->attendanceService->recordAttendance(
            $inscription,
            $validated,
            $this->authenticatedUserId($request),
        );

        return AttendanceResource::make($attendance)
            ->response()
            ->setStatusCode(201);
    }

    public function index(AttendanceIndexRequest $request, int $classe): JsonResponse
    {
        $resolvedClass = $this->resolveClass($classe);
        $validated = $request->validated();

        $this->ensurePeriodBelongsToClass(
            $resolvedClass,
            $validated['periode_id'] ?? null,
        );

        $attendance = $this->attendanceService->getAttendance(
            $resolvedClass,
            $validated['date'] ?? null,
            $validated['periode_id'] ?? null,
            $validated['per_page'] ?? 50,
        );

        return AttendanceResource::collection($attendance)->response();
    }

    public function showStudent(
        StudentAttendanceIndexRequest $request,
        int $inscription,
    ): JsonResponse {
        $resolvedInscription = Inscription::query()
            ->whereHas(
                'classe',
                fn ($query) => $query->where(
                    'etablissement_id',
                    $this->establishmentContext->id(),
                ),
            )
            ->with(['classe', 'eleve'])
            ->findOrFail($inscription);
        $validated = $request->validated();

        $attendance = $this->attendanceService->getStudentAttendance(
            $resolvedInscription,
            $validated['start_date'] ?? null,
            $validated['end_date'] ?? null,
            $validated['per_page'] ?? 50,
        );

        return AttendanceResource::collection($attendance)->response();
    }

    private function resolveClass(int $classId): Classe
    {
        return Classe::query()
            ->where('etablissement_id', $this->establishmentContext->id())
            ->findOrFail($classId);
    }

    private function ensurePeriodBelongsToClass(Classe $classe, ?int $periodId): void
    {
        if ($periodId === null) {
            return;
        }

        ModelePeriode::query()
            ->whereIn(
                'modele_bulletin_id',
                $classe->modeleBulletins()->select('modeles_bulletin.id'),
            )
            ->findOrFail($periodId);
    }

    private function authenticatedUserId(StoreAttendanceRequest $request): int
    {
        $user = $request->user();

        if (! $user instanceof Utilisateur) {
            throw new AuthenticationException;
        }

        return $user->getKey();
    }
}
