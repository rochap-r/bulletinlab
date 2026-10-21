<?php

namespace App\Http\Requests\Api\V1\Teacher;

use App\Domains\Tenant\Contexts\EstablishmentContext;
use App\Models\Classe;
use App\Models\Utilisateur;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

#[FailOnUnknownFields]
final class MyClassRankingRequest extends FormRequest
{
    public function schoolClass(): Classe
    {
        return Classe::query()->where('etablissement_id', app(EstablishmentContext::class)->id())
            ->findOrFail($this->route('classe'));
    }

    public function authorize(): bool
    {
        return $this->user() instanceof Utilisateur
            && Gate::forUser($this->user())->allows('manageOwnClassStudents', $this->schoolClass());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['periode_id' => ['sometimes', 'integer', 'min:1']];
    }
}
