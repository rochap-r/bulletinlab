<?php

namespace App\Observers;

use App\Domains\Responsable\Services\ResponsableStudentLinkSyncService;
use App\Models\Eleve;

final readonly class EleveObserver
{
    public function __construct(
        private ResponsableStudentLinkSyncService $linkSyncService,
    ) {}

    public function created(Eleve $eleve): void
    {
        $this->linkSyncService->syncStudent($eleve);
    }

    public function updated(Eleve $eleve): void
    {
        if ($eleve->wasChanged(['telephone_parent', 'telephone_parent_normalise'])) {
            $this->linkSyncService->syncStudent($eleve);
        }
    }
}
