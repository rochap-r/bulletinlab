<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Teacher\Services\ListMyCourseAssignmentsService;
use App\Domains\Tenant\Contexts\EstablishmentContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Teacher\MyCourseAssignmentIndexRequest;
use App\Http\Resources\Api\V1\MyCourseAssignmentResource;
use Illuminate\Http\JsonResponse;

final class MyCourseAssignmentController extends Controller
{
    public function __construct(
        private ListMyCourseAssignmentsService $listAssignmentsService,
        private EstablishmentContext $establishmentContext,
    ) {}

    public function __invoke(
        MyCourseAssignmentIndexRequest $request,
    ): JsonResponse {
        $assignments = $this->listAssignmentsService->paginate(
            $request->actor(),
            $this->establishmentContext->id(),
            $request->integer('per_page', 50),
        );

        return MyCourseAssignmentResource::collection($assignments)->response();
    }
}
