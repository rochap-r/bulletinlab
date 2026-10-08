<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Api\V1\Platform\Concerns\ReturnsConfigurationDetail;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Platform\PlatformTemplateBlocRequest;
use App\Models\SystemTemplateBloc;
use App\Models\SystemTemplateBulletin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ConfigurationBlocController extends Controller
{
    use ReturnsConfigurationDetail;

    public function store(PlatformTemplateBlocRequest $request, int $configuration): JsonResponse
    {
        $template = SystemTemplateBulletin::query()->findOrFail($configuration);
        $template->assertEditable();

        SystemTemplateBloc::query()->create([
            'template_id' => $template->id,
            ...$request->validated(),
        ]);

        return $this->configurationDetailResponse($template->id);
    }

    public function update(PlatformTemplateBlocRequest $request, int $configuration, int $templateBloc): JsonResponse
    {
        $template = SystemTemplateBulletin::query()->findOrFail($configuration);
        $template->assertEditable();

        $blocModel = SystemTemplateBloc::query()
            ->where('template_id', $template->id)
            ->findOrFail($templateBloc);
        $blocModel->update($request->validated());

        return $this->configurationDetailResponse($template->id);
    }

    public function destroy(Request $request, int $configuration, int $templateBloc): JsonResponse
    {
        $template = SystemTemplateBulletin::query()->findOrFail($configuration);
        $template->assertEditable();

        SystemTemplateBloc::query()
            ->where('template_id', $template->id)
            ->findOrFail($templateBloc)
            ->delete();

        return $this->configurationDetailResponse($template->id);
    }
}
