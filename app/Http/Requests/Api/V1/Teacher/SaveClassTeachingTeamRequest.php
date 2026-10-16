<?php

namespace App\Http\Requests\Api\V1\Teacher;

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
final class SaveClassTeachingTeamRequest extends FormRequest
{
    private ?Classe $resolvedClass = null;

    public function authorize(EstablishmentContext $context, Gate $gate): bool
    {
        $actor = $this->user();

        if (! $actor instanceof Utilisateur) {
            return false;
        }

        $classId = filter_var($this->route('classe'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($classId === false) {
            throw (new ModelNotFoundException)->setModel(Classe::class);
        }

        $this->resolvedClass = Classe::query()
            ->where('tenant_id', $actor->tenant_id)
            ->where('etablissement_id', $context->id())
            ->findOrFail($classId);

        return $gate->forUser($actor)->allows('assignCourseTeacher', $this->resolvedClass)
            && $gate->forUser($actor)->allows('assignClassTitular', $this->resolvedClass);
    }

    public function schoolClass(): Classe
    {
        return $this->resolvedClass ?? throw new LogicException('The teaching team class was not resolved.');
    }

    public function actor(): Utilisateur
    {
        $actor = $this->user();

        return $actor instanceof Utilisateur ? $actor : throw new LogicException('The teaching team actor was not resolved.');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'titulaire_id' => ['sometimes', 'integer', 'min:1'],
            'cours' => ['present', 'array', 'max:200'],
            'cours.*' => ['required', 'array:node_id,enseignant_id'],
            'cours.*.node_id' => ['required', 'integer', 'min:1', 'distinct'],
            'cours.*.enseignant_id' => ['required', 'integer', 'min:1'],
        ];
    }
}
