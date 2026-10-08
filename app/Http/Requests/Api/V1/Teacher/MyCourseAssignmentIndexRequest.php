<?php

namespace App\Http\Requests\Api\V1\Teacher;

use App\Models\AttributionCours;
use App\Models\Utilisateur;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use LogicException;

#[FailOnUnknownFields]
final class MyCourseAssignmentIndexRequest extends FormRequest
{
    public function authorize(Gate $gate): bool
    {
        $user = $this->user();

        return $user instanceof Utilisateur
            && $gate->forUser($user)->allows(
                'viewAny',
                AttributionCours::class,
            );
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

        foreach (array_diff(array_keys($this->query()), array_keys($rules)) as $unknownField) {
            $rules[(string) $unknownField] = ['prohibited'];
        }

        return $rules;
    }

    public function actor(): Utilisateur
    {
        $user = $this->user();

        return $user instanceof Utilisateur
            ? $user
            : throw new LogicException(
                'The assignment listing actor was not resolved.',
            );
    }
}
