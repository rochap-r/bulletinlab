<?php

namespace App\Http\Middleware;

use App\Domains\Tenant\Contexts\EstablishmentContext;
use App\Domains\Tenant\Contexts\TenantContext;
use App\Domains\Tenant\Exceptions\EstablishmentNotFoundException;
use App\Models\Etablissement;
use App\Models\Utilisateur;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

final class EstablishmentMiddleware
{
    public function __construct(
        private EstablishmentContext $establishmentContext,
        private TenantContext $tenantContext,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $this->rejectClientManagedContext($request);

        // Sélection explicite d'une école par un admin d'espace via header.
        // Les headers ne traversent pas la validation des FormRequest, donc
        // aucun formulaire existant n'est affecté. L'école est toujours
        // vérifiée côté serveur (même tenant, active).
        if ($user instanceof Utilisateur && $request->headers->has('X-Etablissement')) {
            $selected = $this->resolveSelectedSchool($request, $user);

            $this->establishmentContext->set($selected);

            Context::add(['etablissement_id' => $selected->getKey()]);

            try {
                return $next($request);
            } finally {
                $this->establishmentContext->clear();
            }
        }

        if ($user instanceof Utilisateur
            && $user->etablissement_id === null
            && $this->isTenantAdministrator($user)) {
            // Admin sans établissement propre : doit sélectionner un établissement via header
            throw new EstablishmentNotFoundException;
        }

        if (! $user instanceof Utilisateur || ! $user->etablissement_id) {
            throw new EstablishmentNotFoundException;
        }

        $establishment = Etablissement::withoutGlobalScopes()
            ->whereKey($user->etablissement_id)
            ->where('tenant_id', $this->tenantContext->id())
            ->where('actif', true)
            ->first();

        if (! $establishment) {
            throw new EstablishmentNotFoundException;
        }

        $this->establishmentContext->set($establishment);

        Context::add(['etablissement_id' => $establishment->getKey()]);

        try {
            return $next($request);
        } finally {
            $this->establishmentContext->clear();
        }
    }

    private function isTenantAdministrator(Utilisateur $utilisateur): bool
    {
        return $utilisateur->hasTenantRole(['ADMIN_ECOLE', 'ADMIN_TENANT']);
    }

    /**
     * Résout l'école sélectionnée par header.
     *
     * - Admin d'espace : n'importe quelle école active du tenant (contexte
     *   explicite validé serveur).
     * - Autre profil : uniquement sa propre école (le header est alors
     *   redondant mais inoffensif) ; tout le reste est un contexte forgé.
     * Le contexte d'écriture (body) reste toujours rejeté.
     */
    private function resolveSelectedSchool(Request $request, Utilisateur $user): Etablissement
    {
        $selectedId = filter_var($request->headers->get('X-Etablissement'), FILTER_VALIDATE_INT);

        if ($selectedId === false) {
            throw ValidationException::withMessages([
                'etablissement_id' => ['L’école sélectionnée est invalide.'],
            ]);
        }

        if (! $this->isTenantAdministrator($user)) {
            if ((int) $user->etablissement_id !== $selectedId) {
                throw ValidationException::withMessages([
                    'etablissement_id' => ['La sélection d’école est réservée à l’administrateur de l’espace.'],
                ]);
            }

            $own = Etablissement::withoutGlobalScopes()
                ->whereKey($user->etablissement_id)
                ->where('tenant_id', $this->tenantContext->id())
                ->where('actif', true)
                ->first();

            if (! $own) {
                throw new EstablishmentNotFoundException;
            }

            return $own;
        }

        $school = Etablissement::withoutGlobalScopes()
            ->whereKey($selectedId)
            ->where('tenant_id', $this->tenantContext->id())
            ->where('actif', true)
            ->first();

        if (! $school) {
            throw new EstablishmentNotFoundException;
        }

        return $school;
    }

    private function rejectClientManagedContext(Request $request): void
    {
        $errors = [];

        foreach (['tenant_id', 'etablissement_id'] as $attribute) {
            if ($request->exists($attribute)) {
                $errors[$attribute] = ["The {$attribute} field is managed by the server."];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
