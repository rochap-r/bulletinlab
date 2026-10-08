<?php

namespace App\Http\Requests\Api\V1\Evaluation;

use App\Domains\Evaluation\Services\ListNotesService;
use App\Domains\Tenant\Contexts\EstablishmentContext;
use App\Models\Classe;
use App\Models\Note;
use App\Models\Utilisateur;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use LogicException;

#[FailOnUnknownFields]
final class ClassNoteIndexRequest extends FormRequest
{
    private ?Classe $resolvedClass = null;

    public function authorize(
        Gate $gate,
        EstablishmentContext $establishmentContext,
        ListNotesService $listNotesService,
    ): bool {
        $user = $this->user();

        if (! $user instanceof Utilisateur) {
            return false;
        }

        $classId = filter_var(
            $this->route('classe'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]],
        );

        if ($classId === false) {
            throw (new ModelNotFoundException)->setModel(Classe::class);
        }

        $this->resolvedClass = $listNotesService
            ->findClassForEstablishment(
                $establishmentContext->current(),
                $classId,
            );

        return $gate->forUser($user)->allows(
            'viewClass',
            [Note::class, $this->resolvedClass],
        );
    }

    public function schoolClass(): Classe
    {
        return $this->resolvedClass
            ?? throw new LogicException('The class was not resolved.');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];

        foreach (array_keys($this->query()) as $queryField) {
            if (! array_key_exists($queryField, $rules)) {
                $rules[(string) $queryField] = ['prohibited'];
            }
        }

        return $rules;
    }
}
