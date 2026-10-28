<?php

namespace Database\Seeders;

use App\Enums\StatutLienResponsable;
use App\Enums\TypeLienResponsable;
use App\Models\Eleve;
use App\Models\LienResponsableEleve;
use App\Models\Responsable;
use Illuminate\Database\Seeder;
use LogicException;

class LienResponsableEleveSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Le jeu démo des liens responsables est réservé aux environnements local et testing.');
        }

        $eleve = Eleve::withoutGlobalScopes()
            ->where('actif', true)
            ->orderBy('id')
            ->first();

        if (! $eleve instanceof Eleve) {
            return;
        }

        $responsable = Responsable::query()
            ->where('telephone_normalise', '+243000000011')
            ->first()
            ?? Responsable::factory()->withDefaultPassword()->create([
                'nom' => 'Kabeya',
                'prenom' => 'Marie',
                'telephone' => '+243000000011',
            ]);

        LienResponsableEleve::withoutGlobalScopes()->updateOrCreate(
            [
                'responsable_id' => $responsable->getKey(),
                'eleve_id' => $eleve->getKey(),
            ],
            [
                'tenant_id' => $eleve->tenant_id,
                'type_lien' => TypeLienResponsable::Tuteur,
                'statut' => StatutLienResponsable::Verifie,
                'principal' => true,
                'verifie_at' => now(),
                'methode_verification' => 'VALIDATION_ECOLE',
                'revoque_par' => null,
                'revoque_at' => null,
                'motif_revocation' => null,
            ],
        );
    }
}
