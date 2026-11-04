<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domains\Auth\Services\DemandeReinitialisationMotDePasseService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\StorePasswordResetRequestRequest;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class StorePasswordResetRequestController extends Controller
{
    public function __construct(
        private DemandeReinitialisationMotDePasseService $service,
    ) {}

    public function __invoke(StorePasswordResetRequestRequest $request): JsonResponse
    {
        $this->service->demander(
            telephone: $request->string('telephone')->toString(),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return response()->json([
            'message' => 'Si ce numéro correspond à un compte actif, la demande a été transmise au responsable habilité.',
        ], Response::HTTP_ACCEPTED);
    }
}
