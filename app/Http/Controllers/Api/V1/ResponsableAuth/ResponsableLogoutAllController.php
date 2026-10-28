<?php

namespace App\Http\Controllers\Api\V1\ResponsableAuth;

use App\Domains\Responsable\Contracts\ResponsableAuthServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\Responsable;
use App\Support\AccessTokenCookies;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ResponsableLogoutAllController extends Controller
{
    public function __construct(
        private ResponsableAuthServiceInterface $authService,
    ) {}

    public function __invoke(Request $request): Response
    {
        $responsable = $request->user();
        abort_unless($responsable instanceof Responsable, Response::HTTP_UNAUTHORIZED);

        $this->authService->logoutAll($responsable);

        $response = response()->noContent();
        $response->headers->setCookie(AccessTokenCookies::forget(AccessTokenCookies::RESPONSABLE));

        return $response;
    }
}
