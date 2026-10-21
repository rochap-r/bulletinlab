<?php

namespace App\Http\Requests\Api\V1\Platform;

use App\Models\CalendrierScolaire;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

#[FailOnUnknownFields]
final class PlatformAnneeScolaireRequest extends FormRequest
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
        $annee = $this->route('annee');

        return [
            'calendrier_scolaire_id' => ['required', 'integer', Rule::exists('calendriers_scolaires', 'id')],
            'libelle' => [
                'required',
                'string',
                'max:20',
                'regex:/^\d{4}-\d{4}$/',
                Rule::unique('annees_scolaires')
                    ->ignore($annee),
            ],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['required', 'date'],
        ];
    }

    /**
     * @return array<int, \Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $debut = $this->input('date_debut');
                $fin = $this->input('date_fin');

                if ($debut !== null && $fin !== null && strtotime($fin) <= strtotime($debut)) {
                    $validator->errors()->add(
                        'date_fin',
                        'La date de fin doit être postérieure à la date de début.',
                    );
                }

                $calendarId = $this->input('calendrier_scolaire_id');

                if ($calendarId === null || $debut === null || $fin === null) {
                    return;
                }

                $calendrier = CalendrierScolaire::query()->find($calendarId);

                if ($calendrier === null) {
                    return;
                }

                $inferieur = strtotime($debut) < strtotime($calendrier->date_debut);
                $superieur = strtotime($fin) > strtotime($calendrier->date_fin);

                if ($inferieur || $superieur) {
                    $validator->errors()->add(
                        'date_debut',
                        'Les dates doivent être incluses dans le calendrier scolaire sélectionné.',
                    );
                }
            },
        ];
    }
}
