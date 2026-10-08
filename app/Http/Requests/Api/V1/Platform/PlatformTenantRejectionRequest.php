<?php

namespace App\Http\Requests\Api\V1\Platform;

use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;

#[FailOnUnknownFields]
final class PlatformTenantRejectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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

    public function motif(): string
    {
        return $this->string('motif')->trim()->toString();
    }
}
