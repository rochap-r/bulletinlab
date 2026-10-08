<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Domains\Template\Exceptions\ConfigurationBulletinConflictException;
use App\Enums\TemplateStatut;
use App\Http\Controllers\Api\V1\Platform\Concerns\ReturnsConfigurationDetail;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Platform\PlatformConfigurationBulletinRequest;
use App\Http\Resources\Api\V1\Platform\Configuration\ConfigurationBulletinDetailResource;
use App\Http\Resources\Api\V1\Platform\Configuration\ConfigurationBulletinResource;
use App\Models\Classe;
use App\Models\ModeleBulletin;
use App\Models\SystemTemplateBulletin;
use App\Services\Templates\TemplateCompositionValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

final class ConfigurationBulletinController extends Controller
{
    use ReturnsConfigurationDetail;

    public function index(Request $request): JsonResponse
    {
        $configurations = SystemTemplateBulletin::query()
            ->with([
                'cycle',
                'degre',
                'option',
                'niveauMin',
                'niveauMax',
                'layout',
            ])
            ->withCount([
                'nodes',
                'periodes',
                'nodes as matieres_count' => fn ($query) => $query->whereNotNull('matiere_id'),
            ])
            ->orderBy('cycle_id')
            ->orderBy('nom')
            ->get();

        return ConfigurationBulletinResource::collection($configurations)->response();
    }

    public function store(PlatformConfigurationBulletinRequest $request): JsonResponse
    {
        $template = SystemTemplateBulletin::query()->create($request->validated());

        return ConfigurationBulletinDetailResource::make(
            SystemTemplateBulletin::query()->withComposition()->findOrFail($template->id),
        )->response()->setStatusCode(201);
    }

    public function show(Request $request, int $configuration): JsonResponse
    {
        $template = SystemTemplateBulletin::query()->withComposition()->findOrFail($configuration);

        return ConfigurationBulletinDetailResource::make($template)->response();
    }

    public function update(PlatformConfigurationBulletinRequest $request, int $configuration): JsonResponse
    {
        $template = SystemTemplateBulletin::query()->findOrFail($configuration);
        $template->assertEditable();
        $template->update($request->validated());

        return ConfigurationBulletinDetailResource::make(
            SystemTemplateBulletin::query()->withComposition()->findOrFail($template->id),
        )->response();
    }

    public function destroy(Request $request, int $configuration): Response
    {
        $template = SystemTemplateBulletin::query()->findOrFail($configuration);

        if ($template->statut === TemplateStatut::DEPRECIE) {
            throw new ConfigurationBulletinConflictException(
                'Une configuration dépréciée se purge, elle ne se supprime pas.',
            );
        }

        if (! $template->isEditable()) {
            throw new ConfigurationBulletinConflictException(
                'Une configuration publiée ne peut pas être supprimée. Dépréciez-la d’abord.',
            );
        }

        $this->assertNotReferenced($template->id);

        $template->delete();

        return response()->noContent();
    }

    public function purge(int $configuration): Response
    {
        $template = SystemTemplateBulletin::query()->findOrFail($configuration);

        if ($template->statut !== TemplateStatut::DEPRECIE) {
            throw new ConfigurationBulletinConflictException(
                'Seule une configuration dépréciée peut être purgée.',
            );
        }

        $this->assertNotReferenced($template->id);

        $template->delete();

        return response()->noContent();
    }

    public function validateConfiguration(int $configuration): JsonResponse
    {
        $template = SystemTemplateBulletin::query()->findOrFail($configuration);

        if (! $template->isEditable()) {
            throw new ConfigurationBulletinConflictException(
                'Une configuration publiée ou dépréciée ne peut plus être validée.',
            );
        }

        $this->throwIfBlocked($template);

        $template->update(['statut' => TemplateStatut::VALIDE]);

        return $this->configurationDetailResponse($template->id);
    }

    public function publish(int $configuration): JsonResponse
    {
        $template = SystemTemplateBulletin::query()->findOrFail($configuration);

        if ($template->statut !== TemplateStatut::VALIDE) {
            throw new ConfigurationBulletinConflictException(
                'Seule une configuration validée peut être publiée.',
            );
        }

        $this->throwIfBlocked($template);

        $template->update([
            'statut' => TemplateStatut::PUBLIE,
            'actif' => true,
            'publie_a' => now(),
        ]);

        return $this->configurationDetailResponse($template->id);
    }

    public function deprecate(int $configuration): JsonResponse
    {
        $template = SystemTemplateBulletin::query()->findOrFail($configuration);

        if ($template->statut !== TemplateStatut::PUBLIE) {
            throw new ConfigurationBulletinConflictException(
                'Seule une configuration publiée peut être dépréciée.',
            );
        }

        $template->update([
            'statut' => TemplateStatut::DEPRECIE,
            'actif' => false,
        ]);

        return $this->configurationDetailResponse($template->id);
    }

    public function returnToDraft(int $configuration): JsonResponse
    {
        $template = SystemTemplateBulletin::query()->findOrFail($configuration);

        if ($template->statut !== TemplateStatut::VALIDE) {
            throw new ConfigurationBulletinConflictException(
                'Seule une configuration validée peut revenir au brouillon.',
            );
        }

        $template->update(['statut' => TemplateStatut::BROUILLON]);

        return $this->configurationDetailResponse($template->id);
    }

    private function assertNotReferenced(int $configuration): void
    {
        if (
            Classe::query()->where('system_template_id', $configuration)->exists()
            || ModeleBulletin::query()->where('system_template_id', $configuration)->exists()
        ) {
            throw new ConfigurationBulletinConflictException(
                'Cette configuration est référencée par des classes ou des modèles de bulletin et ne peut pas être supprimée.',
            );
        }
    }

    private function throwIfBlocked(SystemTemplateBulletin $template): void
    {
        $blockers = (new TemplateCompositionValidator)->blockers($template);

        if ($blockers !== []) {
            throw ValidationException::withMessages(['composition' => $blockers]);
        }
    }
}
