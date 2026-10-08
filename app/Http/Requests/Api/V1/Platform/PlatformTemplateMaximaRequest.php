<?php

namespace App\Http\Requests\Api\V1\Platform;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[FailOnUnknownFields]
final class PlatformTemplateMaximaRequest extends FormRequest
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
        $templateId = $this->route('configuration');

        return [
            'slots' => ['required', 'array'],
            'slots.*.node_id' => [
                'required',
                'integer',
                Rule::exists('system_template_nodes', 'id')->where('template_id', $templateId),
            ],
            'slots.*.periode_id' => [
                'required',
                'integer',
                Rule::exists('system_template_periodes', 'id')->where('template_id', $templateId),
            ],
            'slots.*.maxima' => ['nullable', 'numeric', 'min:0'],
            'slots.*.coefficient' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function after(): array
    {
        return [
            function (): void {
                $pairs = collect($this->input('slots', []))
                    ->map(fn (array $slot): string => $slot['node_id'].':'.$slot['periode_id']);

                if ($pairs->count() !== $pairs->unique()->count()) {
                    $this->validator->errors()->add(
                        'slots',
                        'La grille contient des emplacements en double pour la même paire matière/période.',
                    );
                }
            },
        ];
    }
}
