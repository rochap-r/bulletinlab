<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Domains\Auth\Contracts\AuthServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    public function __construct(
        private AuthServiceInterface $authService
    ) {}

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'telephone' => 'required|string',
            'password' => 'required|string',
        ]);

        $result = $this->authService->login($request->telephone, $request->password);

        return response()->json($result);
    }

    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'tenant_id' => 'required|integer|exists:tenants,id',
            'nom' => 'required|string',
            'telephone' => 'required|string',
            'password' => 'required|string|min:6',
            'postnom' => 'nullable|string',
            'prenom' => 'nullable|string',
            'email' => 'nullable|email',
            'etablissement_id' => 'nullable|integer|exists:etablissements,id',
        ]);

        $user = $this->authService->register($request->all());

        return response()->json($user, 201);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout();

        return response()->json(['message' => 'Logged out successfully']);
    }
}
