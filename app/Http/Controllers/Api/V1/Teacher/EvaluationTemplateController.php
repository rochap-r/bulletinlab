<?php

namespace App\Http\Controllers\Api\V1\Teacher;

use App\Domains\Evaluation\Services\EnsureEvaluationTemplateService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Evaluation\EnsureEvaluationTemplateRequest;
use App\Http\Resources\Api\V1\Evaluation\EvaluationResource;
use Illuminate\Http\JsonResponse;

final class EvaluationTemplateController extends Controller
{
    public function __construct(
        private EnsureEvaluationTemplateService $templateService,
    ) {}

    public function __invoke(
        EnsureEvaluationTemplateRequest $request,
    ): JsonResponse {
        $evaluations = $this->templateService->ensure(
            $request->courseAssignment(),
            $request->entrySlot(),
            $request->teacher(),
        );

        return EvaluationResource::collection($evaluations)->response();
    }
}
