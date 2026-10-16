<?php

namespace App\Http\Controllers\Api\V1\Membership;

use App\Domains\Membership\Services\DirectUtilisateurService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Membership\StoreDirectUtilisateurRequest;
use App\Http\Resources\Api\V1\Membership\CreatedDirectUtilisateurResource;
use App\Models\Utilisateur;
use Illuminate\Http\JsonResponse;

final class StoreDirectUtilisateurController extends Controller
{
    public function __construct(private readonly DirectUtilisateurService $service) {}

    public function __invoke(StoreDirectUtilisateurRequest $request): JsonResponse
    {
        /** @var Utilisateur $actor */
        $actor = $request->user();

        return CreatedDirectUtilisateurResource::make(
            $this->service->create($request->creationData(), $actor),
        )->response()
            ->setStatusCode(201)
            ->header('Cache-Control', 'no-store, private');
    }
}
