<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Platform\Configuration\ReferenceResource;
use App\Models\SystemBlocSpecial;
use Illuminate\Http\JsonResponse;

final class BlocSpecialController extends Controller
{
    public function index(): JsonResponse
    {
        $blocs = SystemBlocSpecial::query()
            ->orderBy('nom')
            ->get();

        return ReferenceResource::collection($blocs)->response();
    }
}
