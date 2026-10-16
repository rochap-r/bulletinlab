<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Teacher\Services\ListMyClassesService;
use App\Domains\Tenant\Contexts\EstablishmentContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Teacher\MyClassIndexRequest;
use App\Http\Resources\Api\V1\MyClassResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class MyClassController extends Controller
{
    public function __construct(
        private ListMyClassesService $listMyClassesService,
        private EstablishmentContext $establishmentContext,
    ) {}

    public function __invoke(MyClassIndexRequest $request): AnonymousResourceCollection
    {
        return MyClassResource::collection($this->listMyClassesService->list(
            $request->actor(),
            $this->establishmentContext->id(),
        ));
    }
}
