<?php

namespace App\Http\Requests\Api\V1\ResponsableAuth;

use App\Models\Responsable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[FailOnUnknownFields]
final class SetInitialResponsablePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof Responsable;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'mot_de_passe' => [
                'required',
                'string',
                'min:6',
                'max:255',
                'confirmed',
                Rule::notIn([(string) config('responsables.default_password', '123456')]),
            ],
        ];
    }
}
