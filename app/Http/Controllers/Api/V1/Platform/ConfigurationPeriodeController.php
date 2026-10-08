<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Api\V1\Platform\Concerns\ReturnsConfigurationDetail;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Platform\PlatformTemplatePeriodeRequest;
use App\Models\SystemTemplateBulletin;
use App\Models\SystemTemplatePeriode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ConfigurationPeriodeController extends Controller
{
    use ReturnsConfigurationDetail;

    public function store(PlatformTemplatePeriodeRequest $request, int $configuration): JsonResponse
    {
        $template = SystemTemplateBulletin::query()->findOrFail($configuration);
        $template->assertEditable();

        SystemTemplatePeriode::query()->create([
            'template_id' => $template->id,
            ...$request->validated(),
        ]);

        return $this->configurationDetailResponse($template->id);
    }

    public function update(PlatformTemplatePeriodeRequest $request, int $configuration, int $periode): JsonResponse
    {
        $template = SystemTemplateBulletin::query()->findOrFail($configuration);
        $template->assertEditable();

        $periodeModel = SystemTemplatePeriode::query()
            ->where('template_id', $template->id)
            ->findOrFail($periode);
        $periodeModel->update($request->validated());

        return $this->configurationDetailResponse($template->id);
    }

    public function destroy(Request $request, int $configuration, int $periode): JsonResponse
    {
        $template = SystemTemplateBulletin::query()->findOrFail($configuration);
        $template->assertEditable();

        SystemTemplatePeriode::query()
            ->where('template_id', $template->id)
            ->findOrFail($periode)
            ->delete();

        return $this->configurationDetailResponse($template->id);
    }
}
