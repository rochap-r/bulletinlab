<?php

namespace App\Http\Requests\Api\V1\Platform;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PlatformTenantIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'statut' => ['nullable', Rule::in(['EN_ATTENTE', 'ESSAI', 'ACTIF', 'SUSPENDU', 'RESILIE', 'REFUSE'])],
        ];
    }

    public function statut(): ?string
    {
        return $this->filled('statut')
            ? $this->string('statut')->toString()
            : null;
    }
}
