<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Teacher\Services\UpsertMyCourseAssignmentNoteService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Teacher\UpsertMyCourseAssignmentNoteRequest;
use App\Http\Resources\Api\V1\MyCourseAssignmentNoteResource;
use Illuminate\Http\JsonResponse;

final class UpsertMyCourseAssignmentNoteController extends Controller
{
    public function __construct(
        private UpsertMyCourseAssignmentNoteService $upsertNoteService,
    ) {}

    public function __invoke(
        UpsertMyCourseAssignmentNoteRequest $request,
    ): JsonResponse {
        /** @var array{
         *     valeur?: int|float|string,
         *     statut_resultat?: string,
         *     commentaire?: ?string,
         *     motif_correction?: ?string,
         *     lock_version?: int
         * } $validated
         */
        $validated = $request->validated();
        $result = $this->upsertNoteService->upsert(
            assignment: $request->courseAssignment(),
            slot: $request->entrySlot(),
            enrollmentId: $request->enrollmentId(),
            teacher: $request->teacher(),
            status: $request->resultStatus(),
            value: $validated['valeur'] ?? null,
            comment: $validated['commentaire'] ?? null,
            correctionReason: $validated['motif_correction'] ?? null,
            expectedVersion: $validated['lock_version'] ?? null,
        );

        return MyCourseAssignmentNoteResource::make($result)->response();
    }
}
