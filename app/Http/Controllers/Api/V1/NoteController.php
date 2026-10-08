<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Evaluation\Services\ListNotesService;
use App\Domains\Tenant\Contexts\EstablishmentContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Evaluation\ClassNoteIndexRequest;
use App\Http\Requests\Api\V1\Evaluation\NoteHistoryIndexRequest;
use App\Http\Requests\Api\V1\Evaluation\NoteIndexRequest;
use App\Http\Requests\Api\V1\Evaluation\ShowNoteRequest;
use App\Http\Resources\Api\V1\NoteHistoryResource;
use App\Http\Resources\Api\V1\NoteResource;
use Illuminate\Http\JsonResponse;

final class NoteController extends Controller
{
    public function __construct(
        private EstablishmentContext $establishmentContext,
        private ListNotesService $listNotesService,
    ) {}

    public function index(NoteIndexRequest $request): JsonResponse
    {
        $notes = $this->listNotesService->paginateForEstablishment(
            $this->establishmentContext->current(),
            $request->integer('per_page', 50),
        );

        return NoteResource::collection($notes)->response();
    }

    public function show(ShowNoteRequest $request): JsonResponse
    {
        $note = $this->listNotesService->findResourceForEstablishment(
            $this->establishmentContext->current(),
            $request->note()->getKey(),
        );

        return NoteResource::make($note)->response();
    }

    public function byClasse(
        ClassNoteIndexRequest $request,
    ): JsonResponse {
        $notes = $this->listNotesService->paginateForClass(
            $this->establishmentContext->current(),
            $request->schoolClass(),
            $request->integer('per_page', 50),
        );

        return NoteResource::collection($notes)->response();
    }

    public function history(
        NoteHistoryIndexRequest $request,
    ): JsonResponse {
        $history = $this->listNotesService->paginateHistory(
            $request->note(),
            $request->integer('per_page', 50),
        );

        return NoteHistoryResource::collection($history)->response();
    }
}
