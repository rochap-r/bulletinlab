<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Api\V1\Platform\Concerns\ReturnsConfigurationDetail;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Platform\PlatformTemplateNodeRequest;
use App\Models\SystemTemplateBulletin;
use App\Models\SystemTemplateNode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ConfigurationNodeController extends Controller
{
    use ReturnsConfigurationDetail;

    public function store(PlatformTemplateNodeRequest $request, int $configuration): JsonResponse
    {
        $template = SystemTemplateBulletin::query()->findOrFail($configuration);
        $template->assertEditable();

        SystemTemplateNode::query()->create([
            'template_id' => $template->id,
            ...$request->validated(),
        ]);

        return $this->configurationDetailResponse($template->id);
    }

    public function update(PlatformTemplateNodeRequest $request, int $configuration, int $node): JsonResponse
    {
        $template = SystemTemplateBulletin::query()->findOrFail($configuration);
        $template->assertEditable();

        $nodeModel = SystemTemplateNode::query()
            ->where('template_id', $template->id)
            ->findOrFail($node);
        $nodeModel->update($request->validated());

        return $this->configurationDetailResponse($template->id);
    }

    public function destroy(Request $request, int $configuration, int $node): JsonResponse
    {
        $template = SystemTemplateBulletin::query()->findOrFail($configuration);
        $template->assertEditable();

        SystemTemplateNode::query()
            ->where('template_id', $template->id)
            ->findOrFail($node)
            ->delete();

        return $this->configurationDetailResponse($template->id);
    }
}
