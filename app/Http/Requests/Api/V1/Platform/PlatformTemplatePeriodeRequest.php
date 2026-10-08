<?php

namespace App\Http\Requests\Api\V1\Platform;

use App\Http\Requests\Api\V1\Platform\Concerns\PreventsParentCycles;
use App\Models\SystemTemplatePeriode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[FailOnUnknownFields]
final class PlatformTemplatePeriodeRequest extends FormRequest
{
    use PreventsParentCycles;

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
            'code' => [
                'required',
                'string',
                'max:60',
                Rule::unique('system_template_periodes', 'code')
                    ->where('template_id', $templateId)
                    ->ignore($this->route('periode')),
            ],
            'nom' => ['required', 'string', 'max:120'],
            'type_periode' => ['required', 'string', Rule::in([
                'PERIODE', 'EXAMEN', 'TRIMESTRE', 'SEMESTRE', 'TOTAL',
                'REPECHAGE', 'EXAMEN_ETAT', 'ENAFEP', 'TENASOSP',
            ])],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('system_template_periodes', 'id')->where('template_id', $templateId),
            ],
            'ordre' => ['sometimes', 'integer', 'min:1'],
            'saisissable' => ['sometimes', 'boolean'],
            'calculable' => ['sometimes', 'boolean'],
            'actif' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (): void {
                $parentId = $this->input('parent_id');
                $templateId = (int) $this->route('configuration');
                $periodeId = $this->route('periode');

                if ($parentId === null) {
                    return;
                }

                $parent = SystemTemplatePeriode::query()
                    ->where('template_id', $templateId)
                    ->find($parentId);

                if ($parent === null) {
                    $this->validator->errors()->add(
                        'parent_id',
                        'La période parente appartient à une autre configuration.',
                    );

                    return;
                }

                if ($parent->type_periode === 'PERIODE') {
                    $this->validator->errors()->add(
                        'parent_id',
                        'Une période de type PERIODE ne peut pas contenir de sous-périodes.',
                    );

                    return;
                }

                $this->rejectParentCycle(
                    SystemTemplatePeriode::class,
                    $periodeId === null ? -1 : (int) $periodeId,
                    $templateId,
                    $parentId,
                );
            },
        ];
    }
}
