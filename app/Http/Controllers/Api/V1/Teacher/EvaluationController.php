<?php

namespace App\Http\Controllers\Api\V1\Teacher;

use App\Domains\Evaluation\Services\EvaluationService;
use App\Domains\Tenant\Contexts\EstablishmentContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Evaluation\EvaluationIndexRequest;
use App\Http\Requests\Api\V1\Evaluation\StoreEvaluationRequest;
use App\Http\Resources\Api\V1\Evaluation\EvaluationResource;
use App\Models\Evaluation;
use App\Models\Utilisateur;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class EvaluationController extends Controller
{
    public function __construct(
        private EvaluationService $evaluationService,
    ) {}

    public function index(EvaluationIndexRequest $request): JsonResponse
    {
        $evaluations = $this->evaluationService->index(
            $request->validated(),
            $request->teacher(),
        );

        return EvaluationResource::collection($evaluations)->response();
    }

    public function store(StoreEvaluationRequest $request): JsonResponse
    {
        $teacher = $request->teacher();

        $evaluation = $this->evaluationService->create(
            $request->validated(),
            $teacher,
        );

        return EvaluationResource::make($evaluation->load(['typeEvaluation', 'periode', 'node', 'classe']))->response();
    }

    public function show(
        Request $request,
        EstablishmentContext $context,
        Evaluation $evaluation,
    ): JsonResponse {
        $teacher = $request->user();

        if (! $teacher instanceof Utilisateur
            || (int) $evaluation->etablissement_id !== $context->id()
            || ! $evaluation->attributionCours()
                ->where('enseignant_id', $teacher->getKey())
                ->exists()) {
            abort(404);
        }

        return EvaluationResource::make(
            $evaluation->load(['typeEvaluation', 'periode', 'node', 'classe', 'resultats.eleve']),
        )->response();
    }
}
