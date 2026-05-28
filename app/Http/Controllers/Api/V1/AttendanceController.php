<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\SchoolYear\Services\AttendanceService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AttendanceResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'inscription_id' => 'required|integer|exists:inscriptions,id',
            'date_presence' => 'required|date',
            'moment' => 'nullable|in:JOURNEE,MATIN,APRES_MIDI',
            'statut' => 'required|in:PRESENT,ABSENT,RETARD,JUSTIFIE',
            'observation' => 'nullable|string',
            'periode_id' => 'nullable|integer|exists:modele_periodes,id',
        ]);

        $data = $request->all();
        $data['tenant_id'] = auth()->user()->tenant_id ?? null;
        $data['etablissement_id'] = auth()->user()->etablissement_id ?? null;
        $data['annee_scolaire_id'] = auth()->user()->annee_scolaire_id ?? null;
        $data['classe_id'] = \App\Models\Inscription::findOrFail($request->inscription_id)->classe_id;
        $data['saisi_par'] = auth()->id();

        $attendance = app(AttendanceService::class)->recordAttendance($data);

        return response()->json(AttendanceResource::make($attendance), 201);
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'classe_id' => 'required|integer|exists:classes,id',
            'date' => 'nullable|date',
            'periode_id' => 'nullable|integer|exists:modele_periodes,id',
        ]);

        $attendance = app(AttendanceService::class)->getAttendance(
            $request->classe_id,
            $request->date,
            $request->periode_id
        );

        return response()->json(AttendanceResource::collection($attendance));
    }

    public function showStudent(int $inscriptionId, Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $attendance = app(AttendanceService::class)->getStudentAttendance(
            $inscriptionId,
            $request->start_date,
            $request->end_date
        );

        return response()->json(AttendanceResource::collection($attendance));
    }
}
