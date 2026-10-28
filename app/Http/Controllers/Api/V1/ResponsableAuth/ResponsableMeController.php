<?php

namespace App\Http\Controllers\Api\V1\ResponsableAuth;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ResponsableAuth\ResponsableResource;
use App\Models\Responsable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ResponsableMeController extends Controller
{
    public function __invoke(Request $request): ResponsableResource
    {
        $responsable = $request->user();
        abort_unless($responsable instanceof Responsable, Response::HTTP_UNAUTHORIZED);

        return new ResponsableResource($responsable);
    }
}
