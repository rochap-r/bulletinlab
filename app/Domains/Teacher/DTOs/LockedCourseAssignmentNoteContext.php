<?php

namespace App\Domains\Teacher\DTOs;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\ModeleBulletin;
use App\Models\ModeleMaximum;
use App\Models\ModeleNode;

final readonly class LockedCourseAssignmentNoteContext
{
    public function __construct(
        public Classe $schoolClass,
        public AnneeScolaire $schoolYear,
        public ModeleBulletin $model,
        public ModeleNode $course,
        public ModeleMaximum $slot,
    ) {}
}
