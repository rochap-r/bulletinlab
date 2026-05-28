<?php

namespace App\Domains\Teacher\Contracts;

interface AssignCourseTeacherServiceInterface
{
    public function assignCourse(int $classeId, int $enseignantId, int $nodeId): void;
}
