<?php

namespace App\Domains\Teacher\Contracts;

use App\Domains\Teacher\DTOs\AssignCourseTeacherData;
use App\Models\AttributionCours;
use App\Models\Classe;
use App\Models\ModeleNode;
use App\Models\Utilisateur;

interface AssignCourseTeacherServiceInterface
{
    public function assignCourse(
        Classe $class,
        ModeleNode $course,
        Utilisateur $actor,
        AssignCourseTeacherData $data,
    ): AttributionCours;
}
