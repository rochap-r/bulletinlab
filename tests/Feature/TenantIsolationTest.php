<?php

use App\Domains\Tenant\Contexts\TenantContext;
use App\Domains\Tenant\Exceptions\TenantNotFoundException;
use App\Models\Etablissement;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('tenant isolation works correctly', function () {
    $tenant1 = Tenant::create(['uuid' => Str::uuid(), 'nom_espace' => 'Tenant 1', 'slug' => 'tenant-1']);
    $tenant2 = Tenant::create(['uuid' => Str::uuid(), 'nom_espace' => 'Tenant 2', 'slug' => 'tenant-2']);

    app(TenantContext::class)->set($tenant1);

    $etablissement1 = Etablissement::create([
        'tenant_id' => $tenant1->id,
        'nom' => 'Etablissement 1',
    ]);

    app(TenantContext::class)->set($tenant2);

    $etablissement2 = Etablissement::create([
        'tenant_id' => $tenant2->id,
        'nom' => 'Etablissement 2',
    ]);

    app(TenantContext::class)->set($tenant1);

    $etablissementsTenant1 = Etablissement::all();
    expect($etablissementsTenant1)->toHaveCount(1);
    expect($etablissementsTenant1->first()->id)->toBe($etablissement1->id);

    app(TenantContext::class)->set($tenant2);

    $etablissementsTenant2 = Etablissement::all();
    expect($etablissementsTenant2)->toHaveCount(1);
    expect($etablissementsTenant2->first()->id)->toBe($etablissement2->id);
});

test('tenant scope is automatically applied to models', function () {
    $tenant1 = Tenant::create(['uuid' => Str::uuid(), 'nom_espace' => 'Tenant 1', 'slug' => 'tenant-1']);
    $tenant2 = Tenant::create(['uuid' => Str::uuid(), 'nom_espace' => 'Tenant 2', 'slug' => 'tenant-2']);

    app(TenantContext::class)->set($tenant1);

    $etablissement1 = Etablissement::create([
        'tenant_id' => $tenant1->id,
        'nom' => 'Etablissement Test 1',
    ]);

    app(TenantContext::class)->set($tenant2);

    $etablissement2 = Etablissement::create([
        'tenant_id' => $tenant2->id,
        'nom' => 'Etablissement Test 2',
    ]);

    app(TenantContext::class)->set($tenant1);

    $etablissements = Etablissement::all();
    expect($etablissements)->toHaveCount(1);
    expect($etablissements->first()->id)->toBe($etablissement1->id);
    expect($etablissements->first()->nom)->toBe('Etablissement Test 1');
});

test('tenant context fails closed when no tenant has been resolved', function () {
    app(TenantContext::class)->current();
})->throws(TenantNotFoundException::class, 'No tenant context found');
