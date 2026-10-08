<?php

namespace App\Http\Requests\Api\V1\Attendance;

use App\Domains\Tenant\Contexts\EstablishmentContext;
use App\Models\Classe;
use App\Models\Utilisateur;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[FailOnUnknownFields]
class StoreAttendanceRequest extends FormRequest
{
    public function authorize(
        EstablishmentContext $establishmentContext,
        Gate $gate,
    ): bool {
        $user = $this->user();

        return $user instanceof Utilisateur
            && $gate->forUser($user)->allows(
                'recordAttendance',
                $this->resolveClass($establishmentContext),
            );
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'inscription_id' => ['required', 'integer'],
            'date_presence' => ['required', 'date'],
            'moment' => ['sometimes', Rule::in(['JOURNEE', 'MATIN', 'APRES_MIDI'])],
            'statut' => ['required', Rule::in(['PRESENT', 'ABSENT', 'RETARD', 'JUSTIFIE'])],
            'observation' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'periode_id' => ['sometimes', 'nullable', 'integer'],
        ];
    }

    private function resolveClass(
        EstablishmentContext $establishmentContext,
    ): Classe {
        $classId = filter_var(
            $this->route('classe'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]],
        );

        if ($classId === false) {
            throw (new ModelNotFoundException)->setModel(Classe::class);
        }

        return Classe::query()
            ->where('etablissement_id', $establishmentContext->id())
            ->findOrFail($classId);
    }
}
