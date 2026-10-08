<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Domains\SchoolYear\Services\PlatformCalendarService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Platform\PlatformCalendrierRequest;
use App\Http\Resources\Api\V1\Platform\PlatformCalendrierResource;
use App\Models\CalendrierScolaire;
use Illuminate\Http\JsonResponse;

final class CalendrierController extends Controller
{
    public function __construct(private PlatformCalendarService $calendarService) {}

    public function index(): JsonResponse
    {
        $calendriers = CalendrierScolaire::query()
            ->with('periodes')
            ->withCount('annees')
            ->withExists('mappings')
            ->orderByDesc('date_debut')
            ->get();

        return PlatformCalendrierResource::collection($calendriers)->response();
    }

    public function show(int $calendrier): JsonResponse
    {
        return PlatformCalendrierResource::make(CalendrierScolaire::query()->with('periodes')->withCount('annees')->withExists('mappings')->findOrFail($calendrier))->response();
    }

    public function store(PlatformCalendrierRequest $request): JsonResponse
    {
        return PlatformCalendrierResource::make($this->calendarService->save(null, $request->validated()))->response()->setStatusCode(201);
    }

    public function update(PlatformCalendrierRequest $request, int $calendrier): JsonResponse
    {
        return PlatformCalendrierResource::make($this->calendarService->save($calendrier, $request->validated()))->response();
    }

    public function destroy(int $calendrier): JsonResponse
    {
        $this->calendarService->delete($calendrier);

        return response()->json(null, 204);
    }
}
