<?php

namespace App\Http\Requests\Api\V1\Classroom;

use App\Domains\SchoolYear\DTOs\ArchiveClassData;
use App\Domains\Tenant\Contexts\EstablishmentContext;
use App\Models\Classe;
use App\Models\Utilisateur;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use LogicException;

#[FailOnUnknownFields]
final class ArchiveClassRequest extends FormRequest
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
            'archive',
            $this->resolvedClass,
        );
    }

    public function schoolClass(): Classe
    {
        return $this->resolvedClass
            ?? throw new LogicException('The class was not resolved.');
    }

    public function actor(): Utilisateur
    {
        $user = $this->user();

        return $user instanceof Utilisateur
            ? $user
            : throw new LogicException('The class archive actor was not resolved.');
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

    public function archiveData(): ArchiveClassData
    {
        return new ArchiveClassData(
            reason: $this->string('motif')->trim()->toString(),
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
