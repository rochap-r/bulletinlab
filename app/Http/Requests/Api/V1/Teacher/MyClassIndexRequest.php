<?php

namespace App\Http\Requests\Api\V1\Teacher;

use App\Models\Utilisateur;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use LogicException;

#[FailOnUnknownFields]
final class MyClassIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof Utilisateur || ! $user->actif || $user->etablissement_id === null) {
            return false;
        }

        return $user->hasTenantRole(['ENSEIGNANT', 'TITULAIRE']);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return ['*' => ['prohibited']];
    }

    public function actor(): Utilisateur
    {
        $user = $this->user();

        return $user instanceof Utilisateur
            ? $user
            : throw new LogicException('The teacher class listing actor was not resolved.');
    }
}
