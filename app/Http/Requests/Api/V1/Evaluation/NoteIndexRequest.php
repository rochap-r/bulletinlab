<?php

namespace App\Http\Requests\Api\V1\Evaluation;

use App\Models\Note;
use App\Models\Utilisateur;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;

#[FailOnUnknownFields]
final class NoteIndexRequest extends FormRequest
{
    public function authorize(Gate $gate): bool
    {
        $user = $this->user();

        return $user instanceof Utilisateur
            && $gate->forUser($user)->allows('viewAny', Note::class);
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
