<?php

namespace App\Http\Requests\Api\V1\Student;

use App\Domains\Student\DTOs\ArchiveStudentData;
use App\Domains\Tenant\Contexts\EstablishmentContext;
use App\Models\Eleve;
use App\Models\Utilisateur;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use LogicException;

#[FailOnUnknownFields]
final class ArchiveStudentRequest extends FormRequest
{
    private ?Eleve $resolvedStudent = null;

    public function authorize(
        EstablishmentContext $establishmentContext,
        Gate $gate,
    ): bool {
        $user = $this->user();

        if (! $user instanceof Utilisateur) {
            return false;
        }

        $this->resolvedStudent = $this->resolveStudent(
            $user,
            $establishmentContext,
        );

        return $gate->forUser($user)->allows(
            'archive',
            $this->resolvedStudent,
        );
    }

    public function student(): Eleve
    {
        return $this->resolvedStudent
            ?? throw new LogicException('The student was not resolved.');
    }

    public function actor(): Utilisateur
    {
        $user = $this->user();

        return $user instanceof Utilisateur
            ? $user
            : throw new LogicException('The archive actor was not resolved.');
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'motif' => ['required', 'string', 'max:500'],
        ];
    }

    public function archiveData(): ArchiveStudentData
    {
        return new ArchiveStudentData(
            reason: $this->string('motif')->trim()->toString(),
        );
    }

    private function resolveStudent(
        Utilisateur $user,
        EstablishmentContext $establishmentContext,
    ): Eleve {
        $studentId = filter_var(
            $this->route('eleve'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]],
        );

        if ($studentId === false) {
            throw (new ModelNotFoundException)->setModel(Eleve::class);
        }

        return Eleve::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('etablissement_id', $establishmentContext->id())
            ->findOrFail($studentId);
    }
}
