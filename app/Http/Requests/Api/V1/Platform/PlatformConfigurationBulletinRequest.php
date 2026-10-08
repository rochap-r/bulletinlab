<?php

namespace App\Http\Requests\Api\V1\Platform;

use App\Models\RefNiveau;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[FailOnUnknownFields]
final class PlatformConfigurationBulletinRequest extends FormRequest
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
        return [
            'code' => [
                'required',
                'string',
                'max:120',
                Rule::unique('system_templates_bulletin', 'code')->ignore($this->route('configuration')),
            ],
            'nom' => ['required', 'string', 'max:180'],
            'cycle_id' => ['required', 'integer', 'exists:ref_cycles,id'],
            'degre_id' => [
                'nullable',
                'integer',
                Rule::exists('ref_degres', 'id')->where('cycle_id', $this->input('cycle_id')),
            ],
            'option_id' => [
                'nullable',
                'integer',
                Rule::exists('ref_options', 'id')->where('cycle_id', $this->input('cycle_id')),
            ],
            'niveau_min_id' => [
                'nullable',
                'integer',
                Rule::exists('ref_niveaux', 'id')->where('cycle_id', $this->input('cycle_id')),
            ],
            'niveau_max_id' => [
                'nullable',
                'integer',
                Rule::exists('ref_niveaux', 'id')->where('cycle_id', $this->input('cycle_id')),
            ],
            'layout_id' => ['required', 'integer', 'exists:system_layouts_bulletin,id'],
            'type_decoupage' => ['required', 'string', Rule::in(['TRIMESTRIEL', 'SEMESTRIEL', 'PERSONNALISE'])],
            'version' => ['sometimes', 'string', 'max:30'],
            'actif' => ['sometimes', 'boolean'],
            'total_general_maxima' => ['sometimes', 'nullable', 'numeric', 'min:0'],
        ];
    }

    public function after(): array
    {
        return [
            function (): void {
                $min = $this->input('niveau_min_id');
                $max = $this->input('niveau_max_id');

                if ($min === null || $max === null) {
                    return;
                }

                $minNumero = RefNiveau::query()->whereKey($min)->value('numero');
                $maxNumero = RefNiveau::query()->whereKey($max)->value('numero');

                if ($minNumero !== null && $maxNumero !== null && $minNumero > $maxNumero) {
                    $this->validator->errors()->add(
                        'niveau_max_id',
                        'Le niveau maximum doit être supérieur ou égal au niveau minimum.',
                    );
                }
            },
        ];
    }
}
