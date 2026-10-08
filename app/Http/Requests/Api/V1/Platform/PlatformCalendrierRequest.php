<?php

namespace App\Http\Requests\Api\V1\Platform;

use App\Models\UtilisateurPlateforme;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

#[FailOnUnknownFields]
final class PlatformCalendrierRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() instanceof UtilisateurPlateforme;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:80', Rule::unique('calendriers_scolaires', 'code')->ignore($this->route('calendrier'))],
            'libelle' => ['required', 'string', 'max:150'],
            'pays' => ['required', 'string', 'max:80'],
            'date_debut' => ['required', 'date_format:Y-m-d'],
            'date_fin' => ['required', 'date_format:Y-m-d', 'after:date_debut'],
            'officiel' => ['required', 'boolean'],
            'actif' => ['required', 'boolean'],
            'periodes' => ['present', 'array', 'max:100'],
            'periodes.*' => ['array:id,code,nom,type_periode,date_debut,date_fin,ordre,saisie_notes_ouverte,periode_active'],
            'periodes.*.id' => ['nullable', 'integer', 'distinct', Rule::exists('calendrier_periodes', 'id')->where('calendrier_scolaire_id', $this->route('calendrier') ?? 0)],
            'periodes.*.code' => ['required', 'string', 'max:60', 'distinct:ignore_case'],
            'periodes.*.nom' => ['required', 'string', 'max:150'],
            'periodes.*.type_periode' => ['required', Rule::in(['PERIODE', 'EXAMEN', 'SEMESTRE', 'TRIMESTRE', 'CONGE', 'DELIBERATION', 'PROCLAMATION'])],
            'periodes.*.date_debut' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_debut'],
            'periodes.*.date_fin' => ['required', 'date_format:Y-m-d', 'after_or_equal:periodes.*.date_debut', 'before_or_equal:date_fin'],
            'periodes.*.ordre' => ['required', 'integer', 'min:1', 'max:10000', 'distinct'],
            'periodes.*.saisie_notes_ouverte' => ['required', 'boolean'],
            'periodes.*.periode_active' => ['required', 'boolean'],
        ];
    }

    /** @return array<int, \Closure> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $active = collect($this->input('periodes', []))->where('periode_active', true)->count();
            if ($active > 1) {
                $validator->errors()->add('periodes', 'Une seule période peut être active à la fois.');
            }
        }];
    }
}
