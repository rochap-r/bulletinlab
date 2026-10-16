<?php

namespace App\Http\Requests\Api\V1\Teacher;

use App\Domains\Tenant\Contexts\EstablishmentContext;
use App\Models\Classe;
use App\Models\Utilisateur;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;
use LogicException;

#[FailOnUnknownFields]
final class CourseAssignmentSpreadsheetRequest extends FormRequest
{
    private ?Classe $resolvedClass = null;

    public function authorize(EstablishmentContext $context): bool
    {
        $actor = $this->user();
        if (! $actor instanceof Utilisateur) {
            return false;
        }
        $id = filter_var($this->route('classe'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw (new ModelNotFoundException)->setModel(Classe::class);
        }
        $this->resolvedClass = Classe::query()->where('tenant_id', $actor->tenant_id)
            ->where('etablissement_id', $context->id())->findOrFail($id);

        return $actor->can('assignCourseTeacher', $this->resolvedClass);
    }

    public function schoolClass(): Classe
    {
        return $this->resolvedClass ?? throw new LogicException('The assignment class was not resolved.');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->isMethod('GET') ? [] : [
            'fichier' => ['required', File::types(['xlsx'])->max('5mb'), 'extensions:xlsx'],
        ];
    }
}
