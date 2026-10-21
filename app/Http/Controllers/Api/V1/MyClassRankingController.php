<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Teacher\Services\GetMyClassRankingsService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Teacher\MyClassRankingRequest;
use Illuminate\Http\JsonResponse;

final class MyClassRankingController extends Controller
{
    public function __construct(private GetMyClassRankingsService $rankingsService) {}

    public function __invoke(MyClassRankingRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->rankingsService->get(
                $request->schoolClass(),
                $request->integer('periode_id') ?: null,
            ),
        ]);
    }
}
