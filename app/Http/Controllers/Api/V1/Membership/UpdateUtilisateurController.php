<?php

namespace App\Http\Controllers\Api\V1\Membership;

use App\Domains\Membership\Services\DirectUtilisateurService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Membership\UpdateUtilisateurRequest;
use App\Http\Resources\Api\V1\Establishment\MembreResource;
use App\Models\Utilisateur;
use Illuminate\Http\JsonResponse;

final class UpdateUtilisateurController extends Controller
{
    public function __construct(private readonly DirectUtilisateurService $service) {}

    public function __invoke(UpdateUtilisateurRequest $request, Utilisateur $utilisateur): JsonResponse
    {
        /** @var Utilisateur $actor */
        $actor = $request->user();

        $misAJour = $this->service->update($utilisateur, $request->updateData(), $actor);

        return MembreResource::make($misAJour)->response();
    }
}
