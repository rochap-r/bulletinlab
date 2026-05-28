<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Domains\Evaluation\Contracts\NoteEntryServiceInterface;
use App\Domains\Evaluation\Services\EncodageProgressService;
use App\Domains\Evaluation\Services\NoteImportService;
use App\Http\Requests\ImportNotesRequest;
use App\Http\Resources\Api\V1\NoteHistoryResource;
use App\Models\Note;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class NoteController extends Controller
{
    public function __construct(
        private NoteEntryServiceInterface $noteService
    ) {}

    public function index(): JsonResponse
    {
        $notes = Note::with('tenant', 'bulletin', 'node', 'periode')->get();
        return response()->json($notes);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'bulletin_id' => 'required|integer|exists:bulletins,id',
            'node_id' => 'required|integer|exists:modele_nodes,id',
            'periode_id' => 'required|integer|exists:modele_periodes,id',
            'valeur' => 'required|numeric',
            'saisie_par' => 'nullable|integer',
            'commentaire' => 'nullable|string',
        ]);

        $note = $this->noteService->saveNote($request->all());

        $bulletin = \App\Models\Bulletin::findOrFail($request->bulletin_id);
        app(EncodageProgressService::class)->updateProgress(
            $bulletin->classe_id,
            $request->node_id,
            $request->periode_id
        );

        return response()->json($note, 201);
    }

    public function show(int $id): JsonResponse
    {
        $note = Note::with('tenant', 'bulletin', 'node', 'periode', 'historique')->findOrFail($id);
        return response()->json($note);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'valeur' => 'required|numeric',
            'saisie_par' => 'nullable|integer',
            'commentaire' => 'nullable|string',
        ]);

        $note = Note::findOrFail($id);
        $updatedNote = $this->noteService->saveNote([
            'bulletin_id' => $note->bulletin_id,
            'node_id' => $note->node_id,
            'periode_id' => $note->periode_id,
            'valeur' => $request->valeur,
            'saisie_par' => $request->saisie_par,
            'commentaire' => $request->commentaire,
        ]);

        $bulletin = \App\Models\Bulletin::findOrFail($note->bulletin_id);
        app(EncodageProgressService::class)->updateProgress(
            $bulletin->classe_id,
            $note->node_id,
            $note->periode_id
        );

        return response()->json($updatedNote);
    }

    public function destroy(int $id): JsonResponse
    {
        $note = Note::findOrFail($id);
        $note->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }

    public function byClasse(int $classeId): JsonResponse
    {
        $notes = Note::whereHas('bulletin', function ($query) use ($classeId) {
            $query->where('classe_id', $classeId);
        })->with('bulletin.eleve', 'node', 'periode')->get();

        return response()->json($notes);
    }

    public function history(int $noteId)
    {
        $history = \App\Models\NoteHistorique::where('note_id', $noteId)
            ->with('modifiePar')
            ->orderByDesc('created_at')
            ->get();

        return NoteHistoryResource::collection($history);
    }

    public function import(ImportNotesRequest $request): JsonResponse
    {
        $results = app(NoteImportService::class)->import(
            $request->file('file'),
            $request->classe_id,
            $request->node_id,
            $request->periode_id
        );

        return response()->json($results, 201);
    }
}
