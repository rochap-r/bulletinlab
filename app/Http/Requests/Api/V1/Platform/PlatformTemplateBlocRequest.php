<?php

namespace App\Http\Requests\Api\V1\Platform;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[FailOnUnknownFields]
final class PlatformTemplateBlocRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $blocIdRules = ['integer', 'exists:system_blocs_speciaux,id'];

        if ($this->route('templateBloc') === null) {
            $blocIdRules = [
                'required',
                ...$blocIdRules,
                Rule::unique('system_template_blocs', 'bloc_id')
                    ->where('template_id', $this->route('configuration')),
            ];
        }

        return [
            'bloc_id' => $blocIdRules,
            'ordre' => ['sometimes', 'integer', 'min:1'],
            'actif' => ['sometimes', 'boolean'],
            'configuration' => ['sometimes', 'array'],
            'configuration.*' => ['sometimes'],
        ];
    }
}
