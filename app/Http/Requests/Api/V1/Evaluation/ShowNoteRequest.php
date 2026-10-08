<?php

namespace App\Http\Requests\Api\V1\Evaluation;

use App\Domains\Evaluation\Services\ListNotesService;
use App\Domains\Tenant\Contexts\EstablishmentContext;
use App\Models\Note;
use App\Models\Utilisateur;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use LogicException;

#[FailOnUnknownFields]
final class ShowNoteRequest extends FormRequest
{
    private ?Note $resolvedNote = null;

    public function authorize(
        Gate $gate,
        EstablishmentContext $establishmentContext,
        ListNotesService $listNotesService,
    ): bool {
        $user = $this->user();

        if (! $user instanceof Utilisateur) {
            return false;
        }

        $noteId = filter_var(
            $this->route('note'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]],
        );

        if ($noteId === false) {
            throw (new ModelNotFoundException)->setModel(Note::class);
        }

        $this->resolvedNote = $listNotesService->findForEstablishment(
            $establishmentContext->current(),
            $noteId,
        );

        return $gate->forUser($user)->allows(
            'view',
            $this->resolvedNote,
        );
    }

    public function note(): Note
    {
        return $this->resolvedNote
            ?? throw new LogicException('The note was not resolved.');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [];

        foreach (array_keys($this->query()) as $queryField) {
            $rules[(string) $queryField] = ['prohibited'];
        }

        return $rules;
    }
}
