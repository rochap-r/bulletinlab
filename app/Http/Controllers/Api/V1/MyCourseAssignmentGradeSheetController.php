<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Teacher\Services\ListMyCourseAssignmentGradeSheetService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Teacher\MyCourseAssignmentGradeSheetRequest;
use App\Http\Resources\Api\V1\MyCourseAssignmentGradeSheetRowResource;
use App\Http\Resources\Api\V1\MyCourseAssignmentSlotResource;
use Illuminate\Http\JsonResponse;

final class MyCourseAssignmentGradeSheetController extends Controller
{
    public function __construct(
        private ListMyCourseAssignmentGradeSheetService $listGradeSheetService,
    ) {}

    public function __invoke(
        MyCourseAssignmentGradeSheetRequest $request,
    ): JsonResponse {
        $rows = $this->listGradeSheetService->paginate(
            $request->courseAssignment(),
            $request->entrySlot(),
            $request->integer('per_page', 50),
        );

        return MyCourseAssignmentGradeSheetRowResource::collection($rows)
            ->additional([
                'creneau' => (new MyCourseAssignmentSlotResource(
                    $request->entrySlot(),
                ))->resolve($request),
            ])
            ->response();
    }
}
