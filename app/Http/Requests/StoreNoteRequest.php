<?php

namespace App\Http\Requests;

use App\Domains\Evaluation\Services\NoteValidationService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreNoteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
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
            'bulletin_id' => 'required|integer|exists:bulletins,id',
            'node_id' => 'required|integer|exists:modele_nodes,id',
            'periode_id' => 'required|integer|exists:modele_periodes,id',
            'valeur' => [
                'required',
                'numeric',
                'min:0',
                function ($attribute, $value, $fail) {
                    $nodeId = $this->input('node_id');
                    $periodeId = $this->input('periode_id');
                    $bulletinId = $this->input('bulletin_id');

                    if ($nodeId && $periodeId && $bulletinId) {
                        try {
                            $service = app(NoteValidationService::class);
                            $node = \App\Models\ModeleNode::findOrFail($nodeId);
                            $bulletin = \App\Models\Bulletin::findOrFail($bulletinId);
                            $classeId = $bulletin->classe_id;
                            $matiereId = $node->matiere_id;

                            if ($matiereId) {
                                $maximum = $service->getMaximum($matiereId, $periodeId, $classeId);

                                if ($value > $maximum) {
                                    $fail("La note ne peut pas dépasser le maximum de {$maximum}.");
                                }
                            }
                        } catch (\Exception $e) {
                            $fail($e->getMessage());
                        }
                    }
                },
            ],
            'saisie_par' => 'nullable|integer',
            'commentaire' => 'nullable|string',
        ];
    }
}
