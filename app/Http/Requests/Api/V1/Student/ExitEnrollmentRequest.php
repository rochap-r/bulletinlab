<?php

namespace App\Http\Requests\Api\V1\Student;

use App\Domains\Student\DTOs\EnrollmentExitData;
use App\Domains\Tenant\Contexts\EstablishmentContext;
use App\Models\Inscription;
use App\Models\Utilisateur;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use LogicException;

#[FailOnUnknownFields]
final class ExitEnrollmentRequest extends FormRequest
{
    private ?Inscription $resolvedEnrollment = null;

    public function authorize(
        EstablishmentContext $establishmentContext,
        Gate $gate,
    ): bool {
        $user = $this->user();

        if (! $user instanceof Utilisateur) {
            return false;
        }

        $this->resolvedEnrollment = $this->resolveEnrollment(
            $user,
            $establishmentContext,
        );

        return $gate->forUser($user)->allows(
            'recordExit',
            $this->resolvedEnrollment,
        );
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $enrollment = $this->enrollment();
        $minimumExitDate = max(array_filter([
            (string) $enrollment->anneeScolaire->date_debut,
            $enrollment->date_inscription
                ? (string) $enrollment->date_inscription
                : null,
        ]));

        return [
            'statut' => ['required', Rule::in(['ABANDON', 'EXCLU'])],
            'date_sortie' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:'.$minimumExitDate,
                'before_or_equal:'.$enrollment->anneeScolaire->date_fin,
            ],
            'motif' => ['required', 'string', 'max:500'],
        ];
    }

    public function enrollment(): Inscription
    {
        return $this->resolvedEnrollment
            ?? throw new LogicException('The enrollment was not resolved.');
    }

    public function actor(): Utilisateur
    {
        $user = $this->user();

        return $user instanceof Utilisateur
            ? $user
            : throw new LogicException('The enrollment exit actor was not resolved.');
    }

    public function exitData(): EnrollmentExitData
    {
        return new EnrollmentExitData(
            status: $this->string('statut')->toString(),
            exitDate: $this->string('date_sortie')->toString(),
            reason: $this->string('motif')->trim()->toString(),
        );
    }

    private function resolveEnrollment(
        Utilisateur $user,
        EstablishmentContext $establishmentContext,
    ): Inscription {
        $enrollmentId = filter_var(
            $this->route('inscription'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]],
        );

        if ($enrollmentId === false) {
            throw (new ModelNotFoundException)->setModel(Inscription::class);
        }

        return Inscription::query()
            ->where('tenant_id', $user->tenant_id)
            ->whereHas(
                'classe',
                fn ($query) => $query->where(
                    'etablissement_id',
                    $establishmentContext->id(),
                ),
            )
            ->with(['classe', 'anneeScolaire'])
            ->findOrFail($enrollmentId);
    }
}
