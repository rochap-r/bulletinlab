<?php

namespace App\Http\Requests\Api\V1\Student;

use App\Domains\Student\DTOs\EnrollmentData;
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

#[FailOnUnknownFields]
class InscriptionRequest extends FormRequest
{
    public function authorize(
        EstablishmentContext $establishmentContext,
        Gate $gate,
    ): bool {
        $user = $this->user();

        return $user instanceof Utilisateur
            && $gate->forUser($user)->allows(
                'createEnrollment',
                $this->resolveClass($establishmentContext),
            );
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();
        $establishmentId = app(EstablishmentContext::class)->id();

        return [
            'eleve_id' => [
                'required',
                'integer',
                Rule::exists('eleves', 'id')
                    ->where(fn (Builder $query): Builder => $query
                        ->where('tenant_id', $tenantId)
                        ->where('etablissement_id', $establishmentId)
                        ->where('actif', true)),
            ],
            'numero_inscription' => ['sometimes', 'nullable', 'string', 'max:80'],
            'date_inscription' => ['sometimes', 'nullable', 'date'],
        ];
    }

    public function enrollmentData(): EnrollmentData
    {
        $validated = $this->validated();

        return new EnrollmentData(
            studentId: (int) $validated['eleve_id'],
            registrationNumber: $this->filled('numero_inscription')
                ? trim($this->string('numero_inscription')->toString())
                : null,
            enrollmentDate: $validated['date_inscription'] ?? today()->toDateString(),
        );
    }

    private function resolveClass(
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
            ->where('etablissement_id', $establishmentContext->id())
            ->findOrFail($classId);
    }
}
