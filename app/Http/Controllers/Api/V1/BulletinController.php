<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Bulletin\Services\BulletinCompositionService;
use App\Domains\Bulletin\Services\ListBulletinsService;
use App\Domains\Bulletin\Services\PreviewBulletinCalculationService;
use App\Domains\Bulletin\Services\PublishBulletinCalculationService;
use App\Domains\Tenant\Contexts\EstablishmentContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Bulletin\ClassBulletinIndexRequest;
use App\Http\Requests\Api\V1\Bulletin\CreateBulletinRectificationRequest;
use App\Http\Requests\Api\V1\Bulletin\PreviewBulletinCalculationRequest;
use App\Http\Requests\Api\V1\Bulletin\PublishBulletinCalculationRequest;
use App\Http\Requests\Api\V1\Bulletin\ShowBulletinRequest;
use App\Http\Requests\Api\V1\Student\ShowEnrollmentReportCardRequest;
use App\Http\Resources\Api\V1\BulletinCalculationPreviewResource;
use App\Http\Resources\Api\V1\BulletinCompositionResource;
use App\Http\Resources\Api\V1\BulletinPublicationResource;
use App\Http\Resources\Api\V1\BulletinResource;
use Illuminate\Http\JsonResponse;

final class BulletinController extends Controller
{
    public function __construct(
        private EstablishmentContext $establishmentContext,
        private BulletinCompositionService $bulletinCompositionService,
        private ListBulletinsService $listBulletinsService,
        private PreviewBulletinCalculationService $previewService,
        private PublishBulletinCalculationService $publishService,
    ) {}

    public function previewCalculation(
        PreviewBulletinCalculationRequest $request,
    ): JsonResponse {
        $result = $this->previewService->preview(
            $this->establishmentContext->current(),
            $request->bulletin()->getKey(),
            $request->period()->getKey(),
        );

        return BulletinCalculationPreviewResource::make($result)->response();
    }

    public function publishCalculation(
        PublishBulletinCalculationRequest $request,
    ): JsonResponse {
        $result = $this->publishService->publish(
            $request->bulletin(),
            $request->bulletin()->classe,
            $request->period(),
            $request->user(),
        );

        return BulletinPublicationResource::make($result)->response();
    }

    public function rectifyPublication(
        CreateBulletinRectificationRequest $request,
    ): JsonResponse {
        $result = $this->publishService->rectify(
            $request->bulletin(),
            $request->bulletin()->classe,
            $request->period(),
            $request->user(),
            $request->motif(),
        );

        return BulletinPublicationResource::make($result)
            ->response()
            ->setStatusCode(200);
    }

    public function show(ShowBulletinRequest $request): JsonResponse
    {
        return BulletinResource::make($request->bulletin())->response();
    }

    public function showForEnrollment(
        ShowEnrollmentReportCardRequest $request,
    ): JsonResponse {
        $reportCard = $this->bulletinCompositionService->compose(
            $request->enrollment(),
        );

        return BulletinCompositionResource::make($reportCard)->response();
    }

    public function byClasse(
        ClassBulletinIndexRequest $request,
    ): JsonResponse {
        $bulletins = $this->listBulletinsService->paginateForClass(
            $this->establishmentContext->current(),
            $request->schoolClass(),
            $request->integer('per_page', 50),
            $request->decisionStatus(),
        );

        return BulletinResource::collection($bulletins)->response();
    }
}
