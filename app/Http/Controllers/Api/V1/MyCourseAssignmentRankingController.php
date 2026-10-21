<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Teacher\Services\GetMyCourseAssignmentRankingsService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Teacher\MyCourseAssignmentRankingRequest;
use Illuminate\Http\JsonResponse;

final class MyCourseAssignmentRankingController extends Controller
{
    public function __construct(private GetMyCourseAssignmentRankingsService $rankingsService) {}

    public function __invoke(MyCourseAssignmentRankingRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->rankingsService->get($request->courseAssignment(), $request->integer('periode_id') ?: null)]);
    }
}
