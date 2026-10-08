<?php

namespace App\Http\Controllers\Api\V1\Platform\Concerns;

use App\Http\Resources\Api\V1\Platform\Configuration\ConfigurationBulletinDetailResource;
use App\Models\SystemTemplateBulletin;
use Illuminate\Http\JsonResponse;

trait ReturnsConfigurationDetail
{
    private function configurationDetailResponse(int $templateId): JsonResponse
    {
        return ConfigurationBulletinDetailResource::make(
            SystemTemplateBulletin::query()->withComposition()->findOrFail($templateId),
        )->response();
    }
}
