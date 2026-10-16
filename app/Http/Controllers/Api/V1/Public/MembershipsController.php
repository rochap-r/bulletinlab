<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Utilisateur;
use App\Support\PhoneNumberNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MembershipsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'telephone' => ['required', 'string', 'max:32'],
        ]);

        try {
            $telephone = PhoneNumberNormalizer::normalize($validated['telephone']);
        } catch (\InvalidArgumentException) {
            return response()->json(['data' => []]);
        }

        $memberships = Utilisateur::withoutGlobalScopes()
            ->where('telephone_normalise', $telephone)
            ->where('actif', true)
            ->whereHas('tenant', fn ($query) => $query->whereIn('statut', ['EN_ATTENTE', 'ESSAI', 'ACTIF']))
            ->with(['roles.permissions', 'tenant'])
            ->orderBy('id')
            ->get()
            ->map(function (Utilisateur $utilisateur): array {
                $roles = $utilisateur->roles
                    ->filter(fn (Role $role): bool => $role->tenant_id === null || (int) $role->tenant_id === (int) $utilisateur->tenant_id);

                return [
                    'tenant_id' => $utilisateur->tenant_id,
                    'slug' => $utilisateur->tenant->slug,
                    'nom_espace' => $utilisateur->tenant->nom_espace,
                    'roles' => $roles->pluck('code')->unique()->values()->all(),
                    'permissions' => $roles
                        ->flatMap(fn (Role $role): array => $role->relationLoaded('permissions')
                            ? $role->permissions->pluck('code')->all()
                            : [])
                        ->unique()->values()->all(),
                ];
            })
            ->values()
            ->all();

        return response()->json(['data' => $memberships]);
    }
}
