<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Domains\Responsable\Services\ResponsableMonitoringService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class ResponsableMonitoringController extends Controller
{
    public function __construct(
        private ResponsableMonitoringService $monitoringService,
    ) {}

    public function __invoke(): JsonResponse
    {
        return response()->json([
            'data' => $this->monitoringService->snapshot(),
            'generated_at' => now()->toISOString(),
        ]);
    }
}
