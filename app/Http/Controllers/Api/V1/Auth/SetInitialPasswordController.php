<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domains\Auth\Services\PasswordService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\SetInitialPasswordRequest;
use App\Models\Utilisateur;
use Illuminate\Http\JsonResponse;

final class SetInitialPasswordController extends Controller
{
    public function __construct(private readonly PasswordService $service) {}

    public function __invoke(SetInitialPasswordRequest $request): JsonResponse
    {
        /** @var Utilisateur $user */
        $user = $request->user();

        $this->service->setInitial(
            $user,
            $request->string('mot_de_passe')->toString(),
        );

        return response()->json([
            'data' => ['doit_changer_mot_de_passe' => false],
        ])->header('Cache-Control', 'no-store, private');
    }
}
