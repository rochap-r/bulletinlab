<?php

use App\Domains\Tenant\Contexts\TenantContext;
use App\Enums\StatutLienResponsable;
use App\Enums\TypeLienResponsable;
use App\Models\Eleve;
use App\Models\Etablissement;
use App\Models\LienResponsableEleve;
use App\Models\Responsable;
use App\Models\Tenant;
use Database\Seeders\LienResponsableEleveSeeder;
use Database\Seeders\ResponsableSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function responsableTenant(string $slug): Tenant
{
    return Tenant::query()->create([
        'uuid' => (string) Str::uuid(),
        'nom_espace' => Str::headline($slug),
        'slug' => $slug,
        'type_tenant' => 'ECOLE',
        'statut' => 'ACTIF',
    ]);
}

function responsableStudent(Tenant $tenant, string $name): Eleve
{
    $school = Etablissement::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->getKey(),
        'nom' => 'École '.$name,
        'type_etablissement' => 'SECONDAIRE',
        'actif' => true,
    ]);

    return Eleve::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->getKey(),
        'etablissement_id' => $school->getKey(),
        'nom' => $name,
        'actif' => true,
    ]);
}

test('a responsable is a global identity with one normalized phone number', function (): void {
    $responsable = Responsable::factory()->create([
        'telephone' => '0812 345 678',
    ]);

    expect($responsable->telephone_normalise)
        ->toBe('+243812345678')
        ->and($responsable->public_id)->toHaveLength(26);

    Responsable::factory()->create([
        'telephone' => '+243812345678',
    ]);
})->throws(QueryException::class);

test('one responsable can have verified links in several tenants while links stay tenant scoped', function (): void {
    $tenantA = responsableTenant('responsable-a');
    $tenantB = responsableTenant('responsable-b');
    $studentA = responsableStudent($tenantA, 'Élève A');
    $studentB = responsableStudent($tenantB, 'Élève B');
    $responsable = Responsable::factory()->create();

    LienResponsableEleve::factory()->forEleve($studentA)->verified()->create([
        'responsable_id' => $responsable->getKey(),
        'type_lien' => TypeLienResponsable::Mere,
    ]);
    LienResponsableEleve::factory()->forEleve($studentB)->verified()->create([
        'responsable_id' => $responsable->getKey(),
        'type_lien' => TypeLienResponsable::Mere,
    ]);

    app(TenantContext::class)->set($tenantA);
    expect(LienResponsableEleve::query()->verified()->pluck('eleve_id')->all())
        ->toBe([$studentA->getKey()]);

    app(TenantContext::class)->set($tenantB);
    expect(LienResponsableEleve::query()->verified()->pluck('eleve_id')->all())
        ->toBe([$studentB->getKey()]);
});

test('the database rejects a guardian link whose tenant differs from the student tenant', function (): void {
    $tenantA = responsableTenant('responsable-integrity-a');
    $tenantB = responsableTenant('responsable-integrity-b');
    $studentB = responsableStudent($tenantB, 'Élève B');
    $responsable = Responsable::factory()->create();

    LienResponsableEleve::query()->create([
        'tenant_id' => $tenantA->getKey(),
        'responsable_id' => $responsable->getKey(),
        'eleve_id' => $studentB->getKey(),
        'type_lien' => TypeLienResponsable::Tuteur,
        'statut' => StatutLienResponsable::EnAttente,
    ]);
})->throws(QueryException::class);

test('a pending link never grants verified access', function (): void {
    $tenant = responsableTenant('responsable-pending');
    $student = responsableStudent($tenant, 'Élève');
    $link = LienResponsableEleve::factory()->forEleve($student)->create();

    expect($link->isVerified())->toBeFalse()
        ->and($link->statut)->toBe(StatutLienResponsable::EnAttente)
        ->and($link->type_lien)->toBeInstanceOf(TypeLienResponsable::class)
        ->and($link->responsable->eleves)->toBeEmpty();
});

test('a revoked link never grants verified access', function (): void {
    $tenant = responsableTenant('responsable-revoked');
    $student = responsableStudent($tenant, 'Élève');
    $link = LienResponsableEleve::factory()->forEleve($student)->verified()->create([
        'revoque_at' => now(),
    ]);

    app(TenantContext::class)->set($tenant);

    expect($link->isVerified())->toBeFalse()
        ->and(LienResponsableEleve::query()->verified()->exists())->toBeFalse()
        ->and($link->responsable->eleves)->toBeEmpty();
});

test('responsable demo seeders are idempotent', function (): void {
    $tenant = responsableTenant('responsable-seeder');
    responsableStudent($tenant, 'Élève');

    $this->seed([ResponsableSeeder::class, LienResponsableEleveSeeder::class]);
    $this->seed([ResponsableSeeder::class, LienResponsableEleveSeeder::class]);

    expect(Responsable::query()->where('telephone_normalise', '+243000000011')->count())->toBe(1)
        ->and(LienResponsableEleve::withoutGlobalScopes()->count())->toBe(1)
        ->and(LienResponsableEleve::withoutGlobalScopes()->firstOrFail()->isVerified())->toBeTrue();
});
