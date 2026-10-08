<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Platform\Configuration\ReferenceResource;
use App\Models\SystemLayoutBulletin;
use Illuminate\Http\JsonResponse;

final class LayoutBulletinController extends Controller
{
    public function index(): JsonResponse
    {
        $layouts = SystemLayoutBulletin::query()
            ->orderBy('nom')
            ->get();

        return ReferenceResource::collection($layouts)->response();
    }
}
