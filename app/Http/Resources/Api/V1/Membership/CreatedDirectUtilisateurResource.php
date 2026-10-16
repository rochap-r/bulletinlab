<?php

namespace App\Http\Resources\Api\V1\Membership;

use App\Domains\Membership\DTOs\CreatedDirectUtilisateurResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class CreatedDirectUtilisateurResource extends JsonResource
{
    public function __construct(private readonly CreatedDirectUtilisateurResult $result)
    {
        parent::__construct($result);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $utilisateur = $this->result->utilisateur;

        return [
            'utilisateur' => [
                'id' => $utilisateur->id,
                'nom' => $utilisateur->nom,
                'postnom' => $utilisateur->postnom,
                'prenom' => $utilisateur->prenom,
                'telephone' => $utilisateur->telephone_normalise,
                'email' => $utilisateur->email,
                'roles' => $utilisateur->roles->pluck('code')->values(),
                'etablissement' => $utilisateur->etablissement === null ? null : [
                    'id' => $utilisateur->etablissement->id,
                    'nom' => $utilisateur->etablissement->nom,
                ],
                'doit_changer_mot_de_passe' => (bool) $utilisateur->doit_changer_mot_de_passe,
            ],
            'mot_de_passe_temporaire' => $this->result->motDePasseTemporaire,
        ];
    }
}
