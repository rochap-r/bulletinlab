<?php

namespace App\Http\Requests\Api\V1\Teacher;

use App\Domains\Teacher\DTOs\AssignClassTitularData;
use App\Domains\Tenant\Contexts\EstablishmentContext;
use App\Models\Classe;
use App\Models\Utilisateur;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use LogicException;

#[FailOnUnknownFields]
final class AssignClassTitularRequest extends FormRequest
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

        $this->resolvedClass = $this->resolveClass(
            $user,
            $establishmentContext,
        );

        return $gate->forUser($user)->allows(
            'assignClassTitular',
            $this->resolvedClass,
        );
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'titulaire_id' => ['required', 'integer', 'min:1'],
            'date_debut' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'date_fin' => [
                'sometimes',
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:date_debut',
            ],
        ];
    }

    public function schoolClass(): Classe
    {
        return $this->resolvedClass
            ?? throw new LogicException('The titular assignment class was not resolved.');
    }

    public function actor(): Utilisateur
    {
        $user = $this->user();

        return $user instanceof Utilisateur
            ? $user
            : throw new LogicException('The titular assignment actor was not resolved.');
    }

    public function assignmentData(): AssignClassTitularData
    {
        $validated = $this->validated();

        return new AssignClassTitularData(
            titularId: $this->integer('titulaire_id'),
            startDate: $validated['date_debut'] ?? null,
            endDate: $validated['date_fin'] ?? null,
        );
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
