<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Teacher\Services\GetMyClassProgressService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Student\MyClassStudentRequest;
use Illuminate\Http\JsonResponse;

final class MyClassProgressController extends Controller
{
    public function __construct(
        private GetMyClassProgressService $progressService,
    ) {}

    public function __invoke(MyClassStudentRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->progressService->get($request->schoolClass()),
        ]);
    }
}
