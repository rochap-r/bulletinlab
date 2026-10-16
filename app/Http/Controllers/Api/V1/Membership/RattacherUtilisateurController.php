<?php

namespace App\Http\Controllers\Api\V1\Membership;

use App\Domains\Membership\Services\DirectUtilisateurService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Membership\RattacherUtilisateurRequest;
use App\Http\Resources\Api\V1\Auth\UtilisateurResource;
use App\Models\Utilisateur;
use Illuminate\Http\JsonResponse;

final class RattacherUtilisateurController extends Controller
{
    public function __construct(private readonly DirectUtilisateurService $service) {}

    public function __invoke(RattacherUtilisateurRequest $request): JsonResponse
    {
        /** @var Utilisateur $actor */
        $actor = $request->user();

        $result = $this->service->rattacher($request->rattachementData(), $actor);

        return response()->json([
            'data' => [
                'utilisateur' => new UtilisateurResource($result->utilisateur->loadMissing(['roles.permissions', 'tenant', 'etablissement'])),
                'compte_existant' => true,
                'compte_cree' => $result->compteCree,
            ],
        ], $result->compteCree ? 201 : 200);
    }
}
