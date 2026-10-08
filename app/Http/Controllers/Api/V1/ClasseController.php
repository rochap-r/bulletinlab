<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Bulletin\Services\ProclamationService;
use App\Domains\Bulletin\Services\PromotionService;
use App\Domains\Bulletin\Services\PublishClassBulletinsService;
use App\Domains\Bulletin\Services\ValidateClassDeliberationService;
use App\Domains\Evaluation\Services\EncodageProgressService;
use App\Domains\SchoolYear\Services\ArchiveClassService;
use App\Domains\SchoolYear\Services\UpdateClassService;
use App\Domains\Teacher\Contracts\AssignCourseTeacherServiceInterface;
use App\Domains\Teacher\Services\AssignClassTitularService;
use App\Domains\Teacher\Services\ListClassCoursesService;
use App\Domains\Tenant\Contexts\EstablishmentContext;
use App\Domains\Tenant\Contexts\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Classroom\ArchiveClassRequest;
use App\Http\Requests\Api\V1\Classroom\ClassCourseIndexRequest;
use App\Http\Requests\Api\V1\Classroom\ClasseRequest;
use App\Http\Requests\Api\V1\Classroom\DeliberateClassRequest;
use App\Http\Requests\Api\V1\Classroom\GenerateProclamationRequest;
use App\Http\Requests\Api\V1\Classroom\GeneratePromotionRequest;
use App\Http\Requests\Api\V1\Classroom\PublishClassRequest;
use App\Http\Requests\Api\V1\PaginationRequest;
use App\Http\Requests\Api\V1\Teacher\AssignClassTitularRequest;
use App\Http\Requests\Api\V1\Teacher\AssignCourseTeacherRequest;
use App\Http\Resources\Api\V1\ClassCourseResource;
use App\Http\Resources\Api\V1\ClasseResource;
use App\Http\Resources\Api\V1\ClassTitularAssignmentResource;
use App\Http\Resources\Api\V1\CourseAssignmentResource;
use App\Http\Resources\Api\V1\EncodageProgressResource;
use App\Http\Resources\Api\V1\ProclamationResource;
use App\Models\Classe;
use App\Models\ModelePeriode;
use Illuminate\Http\JsonResponse;

final class ClasseController extends Controller
{
    public function __construct(
        private TenantContext $tenantContext,
        private EstablishmentContext $establishmentContext,
        private EncodageProgressService $progressService,
        private ArchiveClassService $archiveClassService,
        private UpdateClassService $updateClassService,
        private AssignClassTitularService $assignClassTitularService,
        private AssignCourseTeacherServiceInterface $assignCourseTeacherService,
        private ListClassCoursesService $listClassCoursesService,
        private ProclamationService $proclamationService,
        private ValidateClassDeliberationService $deliberationService,
        private PublishClassBulletinsService $publishClassService,
        private PromotionService $promotionService,
    ) {}

    public function index(PaginationRequest $request): JsonResponse
    {
        $classes = Classe::query()
            ->where('etablissement_id', $this->establishmentContext->id())
            ->latest('id')
            ->paginate($request->integer('per_page', 50));

        return ClasseResource::collection($classes)->response();
    }

    public function store(ClasseRequest $request): JsonResponse
    {
        $classe = Classe::query()->create([
            ...$request->validated(),
            'tenant_id' => $this->tenantContext->id(),
            'etablissement_id' => $this->establishmentContext->id(),
        ]);

        return ClasseResource::make($classe)
            ->response()
            ->setStatusCode(201);
    }

    public function show(int $classe): JsonResponse
    {
        return ClasseResource::make($this->resolveClass($classe))->response();
    }

    public function update(ClasseRequest $request): JsonResponse
    {
        $class = $this->updateClassService->update(
            $request->schoolClass(),
            $request->validated(),
        );

        return ClasseResource::make($class)->response();
    }

    public function archive(ArchiveClassRequest $request): JsonResponse
    {
        $class = $this->archiveClassService->archive(
            $request->schoolClass(),
            $request->actor(),
            $request->archiveData(),
        );

        return ClasseResource::make($class)->response();
    }

    public function assignCourseTeacher(
        AssignCourseTeacherRequest $request,
    ): JsonResponse {
        $assignment = $this->assignCourseTeacherService->assignCourse(
            $request->schoolClass(),
            $request->course(),
            $request->actor(),
            $request->assignmentData(),
        );

        return CourseAssignmentResource::make($assignment)
            ->response()
            ->setStatusCode(200);
    }

    public function courses(ClassCourseIndexRequest $request): JsonResponse
    {
        $courses = $this->listClassCoursesService->paginate(
            $request->schoolClass(),
            $request->integer('per_page', 50),
        );

        return ClassCourseResource::collection($courses)->response();
    }

    public function assignTitular(
        AssignClassTitularRequest $request,
    ): JsonResponse {
        $assignment = $this->assignClassTitularService->assign(
            $request->schoolClass(),
            $request->actor(),
            $request->assignmentData(),
        );

        return ClassTitularAssignmentResource::make($assignment)
            ->response()
            ->setStatusCode(200);
    }

    public function encodingProgress(int $classe, int $periode): JsonResponse
    {
        $resolvedClass = $this->resolveClass($classe);
        $resolvedPeriod = ModelePeriode::query()
            ->whereHas(
                'modeleBulletin',
                fn ($query) => $query->where('classe_id', $resolvedClass->id),
            )
            ->findOrFail($periode);
        $progress = $this->progressService->getProgress(
            $resolvedClass->id,
            $resolvedPeriod->id,
        );

        return EncodageProgressResource::collection($progress)->response();
    }

    public function generateProclamation(
        GenerateProclamationRequest $request,
    ): JsonResponse {
        $proclamation = $this->proclamationService->generate(
            $this->establishmentContext->current(),
            $request->classe(),
            $request->period(),
            $request->user(),
        );

        return ProclamationResource::make($proclamation)
            ->response()
            ->setStatusCode(200);
    }

    public function deliberate(DeliberateClassRequest $request): JsonResponse
    {
        $result = $this->deliberationService->deliberate(
            $this->establishmentContext->current(),
            $request->classe(),
            $request->period(),
            $request->user(),
            (string) $request->validated('lieu_deliberation'),
            (string) $request->validated('date_deliberation'),
            $request->decisionMap(),
        );

        return response()->json(['data' => $result]);
    }

    public function publishClass(PublishClassRequest $request): JsonResponse
    {
        $result = $this->publishClassService->publishAll(
            $this->establishmentContext->current(),
            $request->classe(),
            $request->period(),
            $request->user(),
        );

        return response()->json(['data' => $result]);
    }

    public function generatePromotion(GeneratePromotionRequest $request): JsonResponse
    {
        $promotion = $this->promotionService->generate(
            $this->establishmentContext->current(),
            $request->classe(),
            $request->period(),
            $request->user(),
            $request->validated('annee_cible_id'),
        );

        $promotion->loadCount('eleves');

        return response()->json(['data' => $promotion]);
    }

    private function resolveClass(int $classId): Classe
    {
        return Classe::query()
            ->where('etablissement_id', $this->establishmentContext->id())
            ->findOrFail($classId);
    }
}
