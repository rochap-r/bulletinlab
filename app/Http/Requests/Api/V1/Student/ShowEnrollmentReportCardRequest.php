<?php

namespace App\Http\Requests\Api\V1\Student;

use App\Domains\Tenant\Contexts\EstablishmentContext;
use App\Models\Inscription;
use App\Models\Utilisateur;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use LogicException;

final class ShowEnrollmentReportCardRequest extends FormRequest
{
    private ?Inscription $resolvedEnrollment = null;

    public function authorize(
        EstablishmentContext $establishmentContext,
    ): bool {
        $user = $this->user();

        if (! $user instanceof Utilisateur) {
            return false;
        }

        $this->resolvedEnrollment = Inscription::query()
            ->where('tenant_id', $user->tenant_id)
            ->whereHas(
                'classe',
                fn ($query) => $query
                    ->where('tenant_id', $user->tenant_id)
                    ->where(
                        'etablissement_id',
                        $establishmentContext->id(),
                    ),
            )
            ->with('classe')
            ->findOrFail($this->route('inscription'));

        return Gate::forUser($user)->allows(
            'viewReportCard',
            $this->resolvedEnrollment,
        );
    }

    public function enrollment(): Inscription
    {
        return $this->resolvedEnrollment
            ?? throw new LogicException('The enrollment was not resolved.');
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [];
    }
}
