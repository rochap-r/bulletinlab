<?php

namespace App\Http\Controllers\Api\V1\Teacher;

use App\Domains\Evaluation\Services\EvaluationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Evaluation\StoreEvaluationResultatsRequest;
use App\Http\Resources\Api\V1\Evaluation\ResultatEvaluationResource;
use App\Models\Evaluation;
use Illuminate\Http\JsonResponse;

final class EvaluationResultatController extends Controller
{
    public function __construct(
        private EvaluationService $evaluationService,
    ) {}

    public function store(
        StoreEvaluationResultatsRequest $request,
        Evaluation $evaluation,
    ): JsonResponse {
        $teacher = $request->teacher();

        $count = $this->evaluationService->storeResultats(
            $evaluation,
            $request->resultats(),
            $teacher,
        );

        $evaluation->load(['resultats.eleve', 'typeEvaluation']);

        return response()->json([
            'message' => "{$count} resultat(s) enregistre(s).",
            'resultats' => ResultatEvaluationResource::collection($evaluation->resultats),
        ]);
    }
}
