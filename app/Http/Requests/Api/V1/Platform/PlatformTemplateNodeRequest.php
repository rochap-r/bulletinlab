<?php

namespace App\Http\Requests\Api\V1\Platform;

use App\Models\SystemTemplateNode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[FailOnUnknownFields]
final class PlatformTemplateNodeRequest extends FormRequest
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
            'type_node' => ['required', 'string', Rule::in([
                'DOMAINE', 'SOUS_DOMAINE', 'RUBRIQUE', 'GROUPE_MAXIMA', 'MATIERE', 'SOUS_TOTAL',
            ])],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('system_template_nodes', 'id')->where('template_id', $templateId),
            ],
            'matiere_id' => ['nullable', 'integer', 'exists:ref_matieres,id'],
            'libelle' => ['required', 'string', 'max:200'],
            'ordre' => ['sometimes', 'integer', 'min:1'],
            'afficher_ligne' => ['sometimes', 'boolean'],
            'calculer_sous_total' => ['sometimes', 'boolean'],
            'obligatoire' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (): void {
                $typeNode = $this->input('type_node');
                $matiereId = $this->input('matiere_id');
                $parentId = $this->input('parent_id');
                $nodeId = $this->route('node');

                if ($typeNode === 'MATIERE' && $matiereId === null) {
                    $this->validator->errors()->add(
                        'matiere_id',
                        'Une matière est requise pour un nœud de type MATIERE.',
                    );

                    return;
                }

                if ($typeNode !== 'MATIERE' && $matiereId !== null) {
                    $this->validator->errors()->add(
                        'matiere_id',
                        'Seul un nœud de type MATIERE peut référencer une matière.',
                    );
                }

                if ($parentId === null) {
                    return;
                }

                $templateId = $this->route('configuration');

                $parent = SystemTemplateNode::query()
                    ->where('template_id', $templateId)
                    ->find($parentId);

                if ($parent === null) {
                    $this->validator->errors()->add(
                        'parent_id',
                        'Le nœud parent appartient à une autre configuration.',
                    );

                    return;
                }

                if (in_array($parent->type_node, ['MATIERE', 'SOUS_TOTAL'], true)) {
                    $this->validator->errors()->add(
                        'parent_id',
                        'Un nœud de type '.$parent->type_node.' ne peut pas avoir d’enfants.',
                    );

                    return;
                }

                if ($nodeId === null) {
                    return;
                }

                if ((int) $parentId === (int) $nodeId) {
                    $this->validator->errors()->add(
                        'parent_id',
                        'Un nœud ne peut pas être son propre parent.',
                    );

                    return;
                }

                $descendantIds = $this->descendantIds((int) $nodeId, (int) $templateId);

                if (in_array((int) $parentId, $descendantIds, true)) {
                    $this->validator->errors()->add(
                        'parent_id',
                        'Un nœud ne peut pas être déplacé sous l’un de ses descendants.',
                    );
                }
            },
        ];
    }

    /**
     * @return array<int, int>
     */
    private function descendantIds(int $nodeId, int $templateId): array
    {
        $nodes = SystemTemplateNode::query()
            ->where('template_id', $templateId)
            ->get(['id', 'parent_id']);

        $childrenByParent = $nodes->reduce(function (array $carry, $node): array {
            $carry[$node->parent_id][] = $node->id;

            return $carry;
        }, []);

        $descendants = [];
        $queue = $childrenByParent[$nodeId] ?? [];

        while ($queue !== []) {
            $current = array_shift($queue);
            $descendants[] = (int) $current;

            foreach ($childrenByParent[$current] ?? [] as $childId) {
                $queue[] = $childId;
            }
        }

        return $descendants;
    }
}
