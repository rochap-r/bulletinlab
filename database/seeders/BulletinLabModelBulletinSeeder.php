<?php

namespace Database\Seeders;

use App\Models\AnneePeriodeMapping;
use App\Models\AnneeScolaire;
use App\Models\AttributionCours;
use App\Models\CalendrierPeriode;
use App\Models\Classe;
use App\Models\Etablissement;
use App\Models\ModeleBulletin;
use App\Models\ModeleNode;
use App\Models\Tenant;
use App\Models\TenantCycleActif;
use App\Models\TitulaireClasse;
use App\Models\Utilisateur;
use App\Services\Templates\CloneSystemTemplateService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BulletinLabModelBulletinSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('slug', 'ecole-test')->firstOrFail();
        $etablissement = Etablissement::where('tenant_id', $tenant->id)
            ->where('code_ministere', 'CST')
            ->firstOrFail();
        $anneeScolaire = AnneeScolaire::where('calendrier_scolaire_id', $this->calendrierRdcId())
            ->firstOrFail();
        $classes = Classe::where('tenant_id', $tenant->id)
            ->whereIn('system_template_id', $this->demoTemplateIds())
            ->orderBy('id')
            ->get();
        $admin = Utilisateur::where('tenant_id', $tenant->id)
            ->where('telephone', '+243000000002')
            ->firstOrFail();
        $enseignant = Utilisateur::where('tenant_id', $tenant->id)
            ->where('telephone', '+243000000003')
            ->firstOrFail();

        $this->mapperPeriodesCalendrier($anneeScolaire);
        $this->activerCyclesTenant($tenant, $anneeScolaire);
        $this->accorderRoles($admin, $enseignant);

        foreach ($classes as $classe) {
            $this->instancierModeleBulletin($classe);
            $this->designerTitulaire($classe, $anneeScolaire, $enseignant);
            $this->attribuerMatieres($classe, $anneeScolaire, $enseignant);
        }
    }

    /**
     * @return list<int>
     */
    private function demoTemplateIds(): array
    {
        return DB::table('system_templates_bulletin')
            ->whereIn('code', [
                'COMMERCIALE_1',
                'COMMERCIALE_2',
                'COMMERCIALE_3',
                'COMMERCIALE_4',
                'CTEB_7',
                'CTEB_8',
            ])
            ->pluck('id')
            ->all();
    }

    private function calendrierRdcId(): int
    {
        return (int) DB::table('calendriers_scolaires')
            ->where('code', 'RDC_2026_2027')
            ->value('id');
    }

    private function instancierModeleBulletin(Classe $classe): void
    {
        $existant = ModeleBulletin::where('classe_id', $classe->id)
            ->where('annee_scolaire_id', $classe->annee_scolaire_id)
            ->where('system_template_id', $classe->system_template_id)
            ->exists();

        if ($existant) {
            return;
        }

        app(CloneSystemTemplateService::class)->cloneForClasse($classe);
    }

    private function mapperPeriodesCalendrier(AnneeScolaire $anneeScolaire): void
    {
        $periodes = CalendrierPeriode::where('calendrier_scolaire_id', $anneeScolaire->calendrier_scolaire_id)
            ->orderBy('ordre')
            ->get();

        foreach ($periodes as $periode) {
            AnneePeriodeMapping::updateOrCreate(
                [
                    'annee_scolaire_id' => $anneeScolaire->id,
                    'calendrier_periode_id' => $periode->id,
                ],
                [
                    'code_periode_template' => $periode->code,
                    'saisie_ouverte' => false,
                    'verrouillee' => false,
                ],
            );
        }
    }

    private function activerCyclesTenant(Tenant $tenant, AnneeScolaire $anneeScolaire): void
    {
        $cycleIds = Classe::where('tenant_id', $tenant->id)
            ->whereNotNull('cycle_id')
            ->distinct()
            ->pluck('cycle_id');

        foreach ($cycleIds as $cycleId) {
            TenantCycleActif::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'annee_scolaire_id' => $anneeScolaire->id,
                    'cycle_id' => $cycleId,
                ],
                ['actif' => true],
            );
        }
    }

    private function accorderRoles(Utilisateur $admin, Utilisateur $enseignant): void
    {
        $this->accorderRole($admin, 'ADMIN_ECOLE');
        $this->accorderRole($enseignant, 'TITULAIRE');
        $this->accorderRole($enseignant, 'ENSEIGNANT');
    }

    private function accorderRole(Utilisateur $utilisateur, string $code): void
    {
        $role = DB::table('roles')->where('code', $code)->whereNull('tenant_id')->first();

        if (! $role) {
            return;
        }

        DB::table('utilisateur_roles')->updateOrInsert(
            ['utilisateur_id' => $utilisateur->id, 'role_id' => $role->id],
            [],
        );
    }

    private function designerTitulaire(
        Classe $classe,
        AnneeScolaire $anneeScolaire,
        Utilisateur $enseignant,
    ): void {
        $dejaTitulaireCetteAnnee = Classe::where('tenant_id', $classe->tenant_id)
            ->where('etablissement_id', $classe->etablissement_id)
            ->where('annee_scolaire_id', $classe->annee_scolaire_id)
            ->where('active', true)
            ->where('id', '!=', $classe->id)
            ->where('titulaire_id', $enseignant->id)
            ->exists();

        if ($dejaTitulaireCetteAnnee) {
            return;
        }

        $existant = TitulaireClasse::where('tenant_id', $classe->tenant_id)
            ->where('classe_id', $classe->id)
            ->exists();

        if ($existant) {
            return;
        }

        TitulaireClasse::create([
            'tenant_id' => $classe->tenant_id,
            'classe_id' => $classe->id,
            'utilisateur_id' => $enseignant->id,
            'date_debut' => $anneeScolaire->date_debut,
            'date_fin' => null,
            'actif' => true,
        ]);

        $classe->update(['titulaire_id' => $enseignant->id]);
    }

    private function attribuerMatieres(
        Classe $classe,
        AnneeScolaire $anneeScolaire,
        Utilisateur $enseignant,
    ): void {
        $modele = ModeleBulletin::where('classe_id', $classe->id)
            ->where('annee_scolaire_id', $classe->annee_scolaire_id)
            ->where('system_template_id', $classe->system_template_id)
            ->firstOrFail();

        $matieres = ModeleNode::where('tenant_id', $classe->tenant_id)
            ->where('modele_bulletin_id', $modele->id)
            ->where('type_node', 'MATIERE')
            ->orderBy('ordre')
            ->get();

        foreach ($matieres as $matiere) {
            $existant = AttributionCours::where('tenant_id', $classe->tenant_id)
                ->where('classe_id', $classe->id)
                ->where('modele_bulletin_id', $modele->id)
                ->where('node_id', $matiere->id)
                ->exists();

            if ($existant) {
                continue;
            }

            AttributionCours::create([
                'tenant_id' => $classe->tenant_id,
                'classe_id' => $classe->id,
                'modele_bulletin_id' => $modele->id,
                'node_id' => $matiere->id,
                'enseignant_id' => $enseignant->id,
                'date_debut' => $anneeScolaire->date_debut,
                'date_fin' => null,
                'actif' => true,
            ]);
        }
    }
}
