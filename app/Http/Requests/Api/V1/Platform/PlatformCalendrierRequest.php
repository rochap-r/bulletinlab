<?php

namespace App\Http\Requests\Api\V1\Platform;

use App\Enums\CalendarAlertLevel;
use App\Enums\CalendarAudience;
use App\Enums\CalendarCycle;
use App\Enums\CalendarEventType;
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
            'profils_cycles' => ['present', 'array', 'min:1', 'max:3'],
            'profils_cycles.*' => ['array:id,cycle_scolaire,mode_decoupage,jours_ouvrables_attendus,heures_minimales,jours_classe'],
            'profils_cycles.*.id' => ['nullable', 'integer', 'distinct', Rule::exists('calendrier_cycle_parametres', 'id')->where('calendrier_scolaire_id', $this->route('calendrier') ?? 0)],
            'profils_cycles.*.cycle_scolaire' => ['required', Rule::enum(CalendarCycle::class)->only([CalendarCycle::Maternelle, CalendarCycle::Primaire, CalendarCycle::SecondaireTechnique]), 'distinct'],
            'profils_cycles.*.mode_decoupage' => ['required', Rule::in(['TRIMESTRIEL', 'SEMESTRIEL'])],
            'profils_cycles.*.jours_ouvrables_attendus' => ['required', 'integer', 'min:1', 'max:366'],
            'profils_cycles.*.heures_minimales' => ['required', 'integer', 'min:1', 'max:5000'],
            'profils_cycles.*.jours_classe' => ['required', 'array', 'min:1', 'max:7'],
            'profils_cycles.*.jours_classe.*' => ['required', Rule::in(['LUNDI', 'MARDI', 'MERCREDI', 'JEUDI', 'VENDREDI', 'SAMEDI'])],
            'periodes' => ['present', 'array', 'max:100'],
            'periodes.*' => ['array:id,code,nom,type_periode,cycle_scolaire,date_debut,date_fin,ordre,saisie_notes_ouverte,periode_active'],
            'periodes.*.id' => ['nullable', 'integer', 'distinct', Rule::exists('calendrier_periodes', 'id')->where('calendrier_scolaire_id', $this->route('calendrier') ?? 0)],
            'periodes.*.code' => ['required', 'string', 'max:60', 'distinct:ignore_case'],
            'periodes.*.nom' => ['required', 'string', 'max:150'],
            'periodes.*.type_periode' => ['required', Rule::in(['PERIODE', 'EXAMEN', 'SEMESTRE', 'TRIMESTRE', 'CONGE', 'DELIBERATION', 'PROCLAMATION'])],
            'periodes.*.cycle_scolaire' => ['required', Rule::enum(CalendarCycle::class)],
            'periodes.*.date_debut' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_debut'],
            'periodes.*.date_fin' => ['required', 'date_format:Y-m-d', 'after_or_equal:periodes.*.date_debut', 'before_or_equal:date_fin'],
            'periodes.*.ordre' => ['required', 'integer', 'min:1', 'max:10000', 'distinct'],
            'periodes.*.saisie_notes_ouverte' => ['required', 'boolean'],
            'periodes.*.periode_active' => ['required', 'boolean'],
            'evenements' => ['present', 'array', 'max:250'],
            'evenements.*' => ['array:id,code,nom,type_evenement,cycle_scolaire,date_debut,date_fin,audiences,niveau_alerte,delai_alerte_jours,description'],
            'evenements.*.id' => ['nullable', 'integer', 'distinct', Rule::exists('calendrier_evenements', 'id')->where('calendrier_scolaire_id', $this->route('calendrier') ?? 0)],
            'evenements.*.code' => ['required', 'string', 'max:80', 'distinct:ignore_case'],
            'evenements.*.nom' => ['required', 'string', 'max:180'],
            'evenements.*.type_evenement' => ['required', Rule::enum(CalendarEventType::class)],
            'evenements.*.cycle_scolaire' => ['required', Rule::enum(CalendarCycle::class)],
            'evenements.*.date_debut' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_debut'],
            'evenements.*.date_fin' => ['required', 'date_format:Y-m-d', 'after_or_equal:evenements.*.date_debut', 'before_or_equal:date_fin'],
            'evenements.*.audiences' => ['required', 'array', 'min:1', 'max:4'],
            'evenements.*.audiences.*' => ['required', Rule::enum(CalendarAudience::class), 'distinct'],
            'evenements.*.niveau_alerte' => ['required', Rule::enum(CalendarAlertLevel::class)],
            'evenements.*.delai_alerte_jours' => ['required', 'integer', 'min:0', 'max:365'],
            'evenements.*.description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<int, \Closure> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $activeByCycle = collect($this->input('periodes', []))
                ->where('periode_active', true)
                ->groupBy('cycle_scolaire');
            if ($activeByCycle->contains(fn ($periods): bool => $periods->count() > 1)) {
                $validator->errors()->add('periodes', 'Une seule période peut être active à la fois pour un même cycle.');
            }

            foreach ($this->input('profils_cycles', []) as $index => $profile) {
                $schoolDays = $profile['jours_classe'] ?? [];
                if (count($schoolDays) !== count(array_unique($schoolDays))) {
                    $validator->errors()->add(
                        "profils_cycles.{$index}.jours_classe",
                        'Chaque jour de classe ne peut apparaître qu’une fois dans un profil de cycle.',
                    );
                }
            }
        }];
    }
}
