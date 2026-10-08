<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Teacher\Services\ImportMyCourseAssignmentNotesService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Teacher\ImportMyCourseAssignmentNotesRequest;
use Illuminate\Http\JsonResponse;

final class ImportMyCourseAssignmentNotesController extends Controller
{
    public function __construct(
        private ImportMyCourseAssignmentNotesService $importNotesService,
    ) {}

    public function __invoke(
        ImportMyCourseAssignmentNotesRequest $request,
    ): JsonResponse {
        /** @var array{motif_correction?: ?string} $validated */
        $validated = $request->validated();
        $result = $this->importNotesService->import(
            file: $request->spreadsheet(),
            assignment: $request->courseAssignment(),
            slot: $request->entrySlot(),
            teacher: $request->teacher(),
            correctionReason: $validated['motif_correction'] ?? null,
        );

        return response()->json([
            'data' => $result->toArray(),
        ]);
    }
}
