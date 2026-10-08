<?php

namespace App\Domains\Evaluation\Services;

use App\Domains\Evaluation\Enums\NoteResultStatus;
use App\Domains\Evaluation\Exceptions\NoteVersionConflictException;
use App\Models\Note;
use Carbon\CarbonInterface;

final class UpdateVersionedNoteService
{
    public function update(
        Note $note,
        ?int $expectedVersion,
        NoteResultStatus $status,
        ?string $value,
        int $actorId,
        CarbonInterface $recordedAt,
        ?string $comment,
    ): Note {
        $expectedVersion = $this->assertExpectedVersion(
            $note,
            $expectedVersion,
        );

        $updated = Note::withoutGlobalScopes()
            ->whereKey($note->getKey())
            ->where('tenant_id', $note->tenant_id)
            ->where('lock_version', $expectedVersion)
            ->update([
                'statut_resultat' => $status,
                'valeur' => $value,
                'saisie_par' => $actorId,
                'date_saisie' => $recordedAt,
                'commentaire' => $comment,
                'lock_version' => $expectedVersion + 1,
                'updated_at' => $recordedAt,
            ]);

        if ($updated !== 1) {
            throw new NoteVersionConflictException(
                'The note was modified by another request.',
            );
        }

        return $note->refresh();
    }

    public function assertExpectedVersion(
        Note $note,
        ?int $expectedVersion,
    ): int {
        if ($expectedVersion === null) {
            throw new NoteVersionConflictException(
                'The current note lock version is required.',
            );
        }

        if ($note->lock_version !== $expectedVersion) {
            throw new NoteVersionConflictException(
                'The note was modified by another request.',
            );
        }

        if ($expectedVersion >= 4294967295) {
            throw new NoteVersionConflictException(
                'The note lock version can no longer be incremented.',
            );
        }

        return $expectedVersion;
    }
}
