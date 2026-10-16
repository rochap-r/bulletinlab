<?php

namespace App\Http\Controllers\Api\V1\Membership;

use App\Domains\Membership\Services\DirectUtilisateurService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Membership\RechercherUtilisateurRequest;
use App\Models\Utilisateur;
use Illuminate\Http\JsonResponse;

final class RechercherUtilisateurController extends Controller
{
    public function __construct(private readonly DirectUtilisateurService $service) {}

    public function __invoke(RechercherUtilisateurRequest $request): JsonResponse
    {
        /** @var Utilisateur $actor */
        $actor = $request->user();

        return response()->json([
            'data' => $this->service->search($request->string('telephone')->toString(), $actor),
        ]);
    }
}
