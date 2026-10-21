<?php

namespace App\Http\Requests\Api\V1\Student;

use App\Domains\Tenant\Contexts\EstablishmentContext;
use App\Models\Classe;
use App\Models\Utilisateur;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

#[FailOnUnknownFields]
final class MyClassStudentRequest extends FormRequest
{
    public function schoolClass(): Classe
    {
        return Classe::query()->where('etablissement_id', app(EstablishmentContext::class)->id())
            ->findOrFail($this->route('classe'));
    }

    public function authorize(): bool
    {
        return $this->user() instanceof Utilisateur
            && Gate::forUser($this->user())->allows('manageOwnClassStudents', $this->schoolClass());
    }

    /** @return array<string, mixed> */
    public static function studentRules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:100', 'not_regex:/^\s*$/u'],
            'postnom' => ['nullable', 'string', 'max:100'],
            'prenom' => ['nullable', 'string', 'max:100'],
            'sexe' => ['nullable', Rule::in(['M', 'F'])],
            'date_naissance' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'lieu_naissance' => ['nullable', 'string', 'max:120'],
            'num_permanent' => ['nullable', 'string', 'max:80'],
            'nom_pere' => ['nullable', 'string', 'max:150'],
            'nom_mere' => ['nullable', 'string', 'max:150'],
            'telephone_parent' => ['nullable', 'string', 'max:30'],
            'adresse' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public static function validationMessages(): array
    {
        return [
            'required' => 'Le champ :attribute est obligatoire.',
            'string' => 'Le champ :attribute doit être un texte.',
            'max.string' => 'Le champ :attribute ne doit pas dépasser :max caractères.',
            'max.file' => 'Le fichier ne doit pas dépasser 5 Mo.',
            'max.array' => 'Le fichier ne doit pas contenir plus de 200 élèves.',
            'min.string' => 'Le champ :attribute doit contenir au moins :min caractères.',
            'in' => 'Le sexe doit être M ou F.',
            'date_format' => 'La date de naissance doit être au format AAAA-MM-JJ.',
            'before_or_equal' => 'La date de naissance ne peut pas être dans le futur.',
            'not_regex' => 'Le nom ne peut pas être vide.',
            'mimes' => 'Choisissez un fichier Excel .xlsx.',
            'extensions' => 'Choisissez un fichier Excel .xlsx.',
            'file' => 'Le fichier doit être un fichier Excel valide.',
            'array' => 'La liste des élèves contient des informations non autorisées.',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return self::validationMessages();
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        $attributes = ['fichier' => 'fichier Excel', 'motif' => 'motif du retrait'];
        foreach (array_keys(self::studentRules()) as $field) {
            $label = str_replace('_', ' ', $field);
            $attributes[$field] = $attributes['eleves.*.'.$field] = $label;
        }

        return $attributes;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        if ($this->routeIs('*.excel.apercu')) {
            return ['fichier' => ['required', 'file', 'mimes:xlsx', 'extensions:xlsx', 'max:5120']];
        }
        if ($this->isMethod('GET')) {
            return ['page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'], 'recherche' => ['sometimes', 'nullable', 'string', 'max:100']];
        }
        if ($this->isMethod('DELETE')) {
            return ['motif' => ['required', 'string', 'min:3', 'max:500']];
        }
        if ($this->isMethod('PUT')) {
            return self::studentRules();
        }
        $rules = ['eleves' => ['required', 'array', 'min:1', 'max:200'],
            'eleves.*' => ['required', 'array:'.implode(',', array_keys(self::studentRules()))]];
        foreach (self::studentRules() as $field => $constraints) {
            $rules['eleves.*.'.$field] = $constraints;
        }

        return $rules;
    }
}
