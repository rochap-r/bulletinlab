<?php

namespace App\Http\Controllers\Api\V1\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Evaluation\TypeEvaluationResource;
use App\Models\TypeEvaluation;
use Illuminate\Http\JsonResponse;

final class TypeEvaluationController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $types = TypeEvaluation::query()
            ->where('actif', true)
            ->orderBy('id')
            ->get();

        return TypeEvaluationResource::collection($types)->response();
    }
}
