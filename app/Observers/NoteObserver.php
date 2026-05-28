<?php

namespace App\Observers;

use App\Models\Note;
use App\Models\NoteHistorique;

class NoteObserver
{
    /**
     * Handle the Note "created" event.
     */
    public function created(Note $note): void
    {
        //
    }

    /**
     * Handle the Note "updated" event.
     */
    public function updated(Note $note): void
    {
        if ($note->isDirty('valeur')) {
            NoteHistorique::create([
                'note_id' => $note->id,
                'ancienne_valeur' => $note->getOriginal('valeur'),
                'nouvelle_valeur' => $note->valeur,
                'modifie_par' => auth()->id(),
                'motif' => 'Modification manuelle',
            ]);
        }
    }

    /**
     * Handle the Note "deleted" event.
     */
    public function deleted(Note $note): void
    {
        //
    }

    /**
     * Handle the Note "restored" event.
     */
    public function restored(Note $note): void
    {
        //
    }

    /**
     * Handle the Note "force deleted" event.
     */
    public function forceDeleted(Note $note): void
    {
        //
    }
}
