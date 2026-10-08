<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Teacher\Services\ListMyCourseAssignmentSlotsService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Teacher\MyCourseAssignmentSlotIndexRequest;
use App\Http\Resources\Api\V1\MyCourseAssignmentSlotResource;
use Illuminate\Http\JsonResponse;

final class MyCourseAssignmentSlotController extends Controller
{
    public function __construct(
        private ListMyCourseAssignmentSlotsService $listSlotsService,
    ) {}

    public function __invoke(
        MyCourseAssignmentSlotIndexRequest $request,
    ): JsonResponse {
        $assignment = $request->courseAssignment();
        $slots = $this->listSlotsService->paginate(
            $assignment,
            $request->integer('per_page', 50),
        );

        return MyCourseAssignmentSlotResource::collection($slots)->response();
    }
}
