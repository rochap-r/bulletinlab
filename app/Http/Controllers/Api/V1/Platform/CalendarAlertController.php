<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Domains\SchoolYear\Services\CalendarPilotageService;
use App\Enums\CalendarAudience;
use App\Enums\CalendarCycle;
use App\Http\Controllers\Controller;
use App\Models\CalendrierScolaire;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class CalendarAlertController extends Controller
{
    public function __construct(private CalendarPilotageService $pilotageService) {}

    public function __invoke(Request $request, CalendrierScolaire $calendrier): JsonResponse
    {
        $validated = $request->validate([
            'cycle' => ['required', Rule::enum(CalendarCycle::class)->except(CalendarCycle::Tous)],
            'audience' => ['required', Rule::enum(CalendarAudience::class)],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $date = isset($validated['date'])
            ? CarbonImmutable::createFromFormat('Y-m-d', $validated['date'], config('app.business_timezone'))->startOfDay()
            : CarbonImmutable::now(config('app.business_timezone'))->startOfDay();
        $cycle = CalendarCycle::from($validated['cycle']);
        $audience = CalendarAudience::from($validated['audience']);

        return response()->json([
            'data' => $this->pilotageService->alerts($calendrier, $cycle, $audience, $date),
            'meta' => [
                'date_reference' => $date->toDateString(),
                'cycle' => $cycle->value,
                'audience' => $audience->value,
            ],
        ]);
    }
}
