<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Api\V1\Platform\Concerns\ReturnsConfigurationDetail;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Platform\PlatformTemplateMaximaRequest;
use App\Models\SystemTemplateBulletin;
use App\Models\SystemTemplateMaximum;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

final class ConfigurationMaximaController extends Controller
{
    use ReturnsConfigurationDetail;

    public function update(PlatformTemplateMaximaRequest $request, int $configuration): JsonResponse
    {
        $template = SystemTemplateBulletin::query()->findOrFail($configuration);
        $template->assertEditable();

        $slots = collect($request->validated()['slots'])
            ->filter(fn (array $slot): bool => $slot['maxima'] !== null)
            ->values();

        DB::transaction(function () use ($template, $slots): void {
            SystemTemplateMaximum::query()
                ->where('template_id', $template->id)
                ->delete();

            foreach ($slots as $slot) {
                SystemTemplateMaximum::query()->create([
                    'template_id' => $template->id,
                    'node_id' => $slot['node_id'],
                    'periode_id' => $slot['periode_id'],
                    'maxima' => $slot['maxima'],
                    'coefficient' => $slot['coefficient'] ?? 1,
                ]);
            }
        });

        return $this->configurationDetailResponse($template->id);
    }
}
