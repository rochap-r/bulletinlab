<?php

namespace App\Http\Requests\Api\V1\ResponsableAuth;

use App\Support\PhoneNumberNormalizer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

#[FailOnUnknownFields]
final class ResponsableLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'telephone' => ['required', 'string', 'max:30'],
            'mot_de_passe' => ['required', 'string', 'max:255'],
        ];
    }

    public function telephone(): string
    {
        return PhoneNumberNormalizer::normalize(
            $this->string('telephone')->trim()->toString(),
        );
    }

    /** @return array<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('telephone')) {
                    return;
                }

                try {
                    PhoneNumberNormalizer::normalize(
                        $this->string('telephone')->trim()->toString(),
                    );
                } catch (\InvalidArgumentException) {
                    $validator->errors()->add(
                        'telephone',
                        'Le numéro de téléphone est invalide.',
                    );
                }
            },
        ];
    }
}
