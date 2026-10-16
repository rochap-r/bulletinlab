<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Tenant\Contexts\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Establishment\StoreComplexeRequest;
use App\Http\Requests\Api\V1\Establishment\StoreEtablissementRequest;
use App\Http\Resources\Api\V1\Establishment\EtablissementResource;
use App\Http\Resources\Api\V1\Establishment\MembreResource;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Etablissement;
use App\Models\Utilisateur;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class EtablissementController extends Controller
{
    public function index(TenantContext $tenantContext): JsonResponse
    {
        Gate::authorize('viewAny', Etablissement::class);

        return EtablissementResource::collection(
            Etablissement::query()->where('tenant_id', $tenantContext->id())->orderBy('nom')->get(),
        )->response();
    }

    public function store(StoreEtablissementRequest $request, TenantContext $tenantContext): JsonResponse
    {
        $etablissement = Etablissement::query()->create([
            ...$request->validated(),
            'tenant_id' => $tenantContext->id(),
            'actif' => true,
        ]);

        return EtablissementResource::make($etablissement)->response()->setStatusCode(201);
    }

    /**
     * Crée d'un coup les écoles d'un complexe scolaire à partir d'un nom
     * de base : « Royal School Academy » + PRIMAIRE donne
     * « Royal School Academy Primaire » (nom repris tel quel sur le bulletin).
     */
    public function storeComplexe(StoreComplexeRequest $request, TenantContext $tenantContext): JsonResponse
    {
        $labels = ['MATERNELLE' => 'Maternelle', 'PRIMAIRE' => 'Primaire', 'SECONDAIRE' => 'Secondaire'];
        $base = $request->string('nom_base')->trim()->toString();

        $ecoles = DB::transaction(function () use ($request, $tenantContext, $labels, $base): Collection {
            $existing = Etablissement::query()
                ->where('tenant_id', $tenantContext->id())
                ->where('actif', true)
                ->whereIn('type_etablissement', $request->orderedTypes())
                ->orderBy('nom')
                ->lockForUpdate()
                ->get(['nom', 'type_etablissement']);

            if ($existing->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'types' => [
                        'Ce niveau existe déjà : '.$existing->map(fn ($school) => $school->nom)->join(', ').'. Décochez-le ou renommez l’école existante.',
                    ],
                ]);
            }

            $created = collect();

            foreach ($request->orderedTypes() as $type) {
                $created->push(Etablissement::query()->create([
                    'tenant_id' => $tenantContext->id(),
                    'nom' => "{$base} {$labels[$type]}",
                    'type_etablissement' => $type,
                    'actif' => true,
                ]));
            }

            return $created;
        });

        return EtablissementResource::collection($ecoles)->response()->setStatusCode(201);
    }

    public function show(TenantContext $tenantContext, string $etablissement): JsonResponse
    {
        $school = $this->resolveSchool($tenantContext, $etablissement);

        Gate::authorize('view', $school);

        return EtablissementResource::make($school)->response();
    }

    public function membres(TenantContext $tenantContext, string $etablissement): JsonResponse
    {
        $school = $this->resolveSchool($tenantContext, $etablissement);

        Gate::authorize('viewMembers', $school);

        $members = Utilisateur::query()
            ->where('tenant_id', $school->tenant_id)
            ->where('etablissement_id', $school->getKey())
            ->orderBy('nom')
            ->with('roles')
            ->get();

        return MembreResource::collection($members)->response();
    }

    public function statistiques(TenantContext $tenantContext, string $etablissement): JsonResponse
    {
        $school = $this->resolveSchool($tenantContext, $etablissement);

        Gate::authorize('viewMembers', $school);

        return response()->json(['data' => [
            'classes' => Classe::query()
                ->where('tenant_id', $school->tenant_id)
                ->where('etablissement_id', $school->getKey())
                ->where('active', true)
                ->count(),
            'eleves' => Eleve::query()
                ->where('tenant_id', $school->tenant_id)
                ->where('etablissement_id', $school->getKey())
                ->where('actif', true)
                ->count(),
            'enseignants' => Utilisateur::query()
                ->where('tenant_id', $school->tenant_id)
                ->where('etablissement_id', $school->getKey())
                ->where('actif', true)
                ->whereHas('roles', fn ($query) => $query
                    ->whereIn('roles.name', ['ENSEIGNANT', 'TITULAIRE'])
                    ->validForTenant($school->tenant_id))
                ->count(),
        ]]);
    }

    private function resolveSchool(TenantContext $tenantContext, string $etablissement): Etablissement
    {
        // Résolution par identifiant public (ULID non devinable) : l'isolation
        // tenant est toujours vérifiée côté serveur, quel que soit l'URL.
        return Etablissement::withoutGlobalScopes()
            ->where('public_id', $etablissement)
            ->where('tenant_id', $tenantContext->id())
            ->firstOrFail();
    }
}
