<?php

namespace App\Domains\Teacher\Services;

use App\Domains\Teacher\Contracts\AssignCourseTeacherServiceInterface;
use App\Domains\Teacher\Exceptions\TeacherException;
use App\Models\Classe;
use App\Models\Utilisateur;
use App\Models\ModeleNode;
use App\Models\AttributionCours;
use Illuminate\Support\Facades\DB;

class AssignCourseTeacherService implements AssignCourseTeacherServiceInterface
{
    public function assignCourse(int $classeId, int $enseignantId, int $nodeId): void
    {
        DB::transaction(function () use ($classeId, $enseignantId, $nodeId) {
            $classe = Classe::findOrFail($classeId);
            $enseignant = Utilisateur::findOrFail($enseignantId);
            $node = ModeleNode::findOrFail($nodeId);

            if ($node->modele_bulletin_id !== $classe->modeleBulletins()->first()->id) {
                throw new TeacherException('Node does not belong to this class model');
            }

            $existing = AttributionCours::where('classe_id', $classeId)
                ->where('node_id', $nodeId)
                ->where('enseignant_id', $enseignantId)
                ->first();

            if ($existing) {
                throw new TeacherException('Course already assigned to this teacher');
            }

            AttributionCours::create([
                'tenant_id' => $classe->tenant_id,
                'classe_id' => $classeId,
                'modele_bulletin_id' => $node->modele_bulletin_id,
                'node_id' => $nodeId,
                'enseignant_id' => $enseignantId,
                'actif' => true,
            ]);
        });
    }
}
