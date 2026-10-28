<?php

namespace App\Http\Controllers\Api\V1\Teacher;

use App\Domains\Evaluation\Services\EvaluationService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Evaluation\EvaluationResource;
use App\Models\Evaluation;
use App\Models\Utilisateur;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PublishEvaluationController extends Controller
{
    public function __construct(
        private EvaluationService $evaluationService,
    ) {}

    public function __invoke(Request $request, Evaluation $evaluation): JsonResponse
    {
        $teacher = $request->user();

        abort_unless($teacher instanceof Utilisateur, 403);

        $published = $this->evaluationService->publish($evaluation, $teacher);

        return EvaluationResource::make(
            $published->load(['typeEvaluation', 'periode', 'node', 'classe']),
        )->response();
    }
}
