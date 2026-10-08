<?php

namespace App\Http\Requests\Api\V1\Classroom;

use App\Domains\Tenant\Contexts\EstablishmentContext;
use App\Domains\Tenant\Contexts\TenantContext;
use App\Models\Classe;
use App\Models\Utilisateur;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use LogicException;

#[FailOnUnknownFields]
final class ClasseRequest extends FormRequest
{
    private ?Classe $resolvedClass = null;

    public function authorize(
        EstablishmentContext $establishmentContext,
        Gate $gate,
    ): bool {
        $user = $this->user();

        if (! $user instanceof Utilisateur) {
            return false;
        }

        if ($this->isMethod('POST')) {
            return $gate->forUser($user)->allows(
                'manageAcademicStructure',
                $establishmentContext->current(),
            );
        }

        $this->resolvedClass = $this->resolveClass(
            $user,
            $establishmentContext,
        );

        return $gate->forUser($user)->allows(
            'update',
            $this->resolvedClass,
        );
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';
        $tenantId = app(TenantContext::class)->id();

        return [
            'annee_scolaire_id' => [
                $required,
                'integer',
                Rule::exists('annees_scolaires', 'id')
                    ->where(fn (Builder $query): Builder => $query->where('tenant_id', $tenantId)),
            ],
            'cycle_id' => [$required, 'integer', Rule::exists('ref_cycles', 'id')],
            'degre_id' => ['sometimes', 'nullable', 'integer', Rule::exists('ref_degres', 'id')],
            'niveau_id' => [$required, 'integer', Rule::exists('ref_niveaux', 'id')],
            'option_id' => ['sometimes', 'nullable', 'integer', Rule::exists('ref_options', 'id')],
            'system_template_id' => [
                $required,
                'integer',
                Rule::exists('system_templates_bulletin', 'id')->where('actif', true),
            ],
            'parallele' => ['sometimes', 'nullable', 'string', 'max:20'],
            'nom' => [$required, 'string', 'max:120'],
            'code' => ['sometimes', 'nullable', 'string', 'max:80'],
            'titulaire_id' => ['prohibited'],
        ];
    }

    public function schoolClass(): Classe
    {
        return $this->resolvedClass
            ?? throw new LogicException('The class was not resolved.');
    }

    private function resolveClass(
        Utilisateur $user,
        EstablishmentContext $establishmentContext,
    ): Classe {
        $classId = filter_var(
            $this->route('classe'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]],
        );

        if ($classId === false) {
            throw (new ModelNotFoundException)->setModel(Classe::class);
        }

        return Classe::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('etablissement_id', $establishmentContext->id())
            ->findOrFail($classId);
    }
}
