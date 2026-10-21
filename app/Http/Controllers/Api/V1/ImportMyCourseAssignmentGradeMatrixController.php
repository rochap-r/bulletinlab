<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Teacher\Services\ImportMyCourseAssignmentGradeMatrixService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Teacher\ImportMyCourseAssignmentGradeMatrixRequest;
use Illuminate\Http\JsonResponse;

final class ImportMyCourseAssignmentGradeMatrixController extends Controller
{
    public function __construct(
        private ImportMyCourseAssignmentGradeMatrixService $importGradeMatrixService,
    ) {}

    public function __invoke(
        ImportMyCourseAssignmentGradeMatrixRequest $request,
    ): JsonResponse {
        /** @var array{motif_correction?: ?string} $validated */
        $validated = $request->validated();
        $result = $this->importGradeMatrixService->import(
            file: $request->spreadsheet(),
            assignment: $request->courseAssignment(),
            columns: $request->matrixColumns(),
            teacher: $request->teacher(),
            correctionReason: $validated['motif_correction'] ?? null,
        );

        return response()->json(['data' => $result->toArray()]);
    }
}
