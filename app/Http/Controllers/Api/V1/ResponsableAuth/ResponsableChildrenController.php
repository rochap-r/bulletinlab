<?php

namespace App\Http\Controllers\Api\V1\ResponsableAuth;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ResponsableAuth\ResponsableChildResource;
use App\Models\LienResponsableEleve;
use App\Models\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ResponsableChildrenController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $responsable = $request->user();
        abort_unless($responsable instanceof Responsable, Response::HTTP_UNAUTHORIZED);

        $links = LienResponsableEleve::query()
            ->verified()
            ->where('responsable_id', $responsable->getKey())
            ->whereHas('tenant', fn ($query) => $query
                ->whereIn('statut', ['ESSAI', 'ACTIF'])
                ->where('espace_parents_actif', true))
            ->with([
                'tenant:id,nom_espace,slug,statut',
                'eleve:id,public_id,tenant_id,etablissement_id,nom,postnom,prenom,sexe',
                'eleve.etablissement:id,public_id,tenant_id,nom,type_etablissement',
                'eleve.activeInscription',
                'eleve.activeInscription.classe:id,public_id,tenant_id,nom,code',
            ])
            ->orderBy('eleve_id')
            ->get();

        return ResponsableChildResource::collection($links)->response();
    }
}
