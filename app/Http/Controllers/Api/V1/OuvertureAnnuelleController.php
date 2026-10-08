<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\SchoolYear\Services\CloseEstablishmentSchoolYearService;
use App\Domains\SchoolYear\Services\OpenEstablishmentSchoolYearService;
use App\Domains\Tenant\Contexts\EstablishmentContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SchoolYear\CloturerOuvertureAnnuelleRequest;
use App\Http\Requests\Api\V1\SchoolYear\OpenOuvertureAnnuelleRequest;
use App\Http\Resources\Api\V1\OuvertureAnnuelleResource;
use Illuminate\Http\JsonResponse;

final class OuvertureAnnuelleController extends Controller
{
    public function __construct(
        private OpenEstablishmentSchoolYearService $openService,
        private CloseEstablishmentSchoolYearService $closeService,
        private EstablishmentContext $establishmentContext,
    ) {}

    public function store(OpenOuvertureAnnuelleRequest $request): JsonResponse
    {
        $ouverture = $this->openService->open(
            $this->establishmentContext->current(),
            $request->user(),
            $request->validated('annee_scolaire_id'),
            $request->validated('cycles', []),
        );

        return OuvertureAnnuelleResource::make($ouverture)
            ->response()
            ->setStatusCode(201);
    }

    public function cloture(CloturerOuvertureAnnuelleRequest $request): JsonResponse
    {
        $ouverture = $this->closeService->close(
            $request->ouverture(),
            $request->user(),
            $request->validated('motif'),
        );

        return OuvertureAnnuelleResource::make($ouverture)->response();
    }
}
