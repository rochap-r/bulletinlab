<?php

namespace App\Domains\SchoolYear\Services;

use App\Models\Classe;
use App\Models\RefCycle;
use App\Models\RefOption;
use App\Services\Templates\CloneSystemTemplateService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class OrganizeSchoolClassesService
{
    public function __construct(
        private ResolveClassTemplateService $resolveClassTemplateService,
        private DatabaseManager $database,
        private CloneSystemTemplateService $cloneSystemTemplateService,
    ) {}

    /**
     * @param  list<array{cycle_id: int, option_id: int|null}>  $organisations
     * @return Collection<int, Classe>
     */
    public function organize(
        int $tenantId,
        int $establishmentId,
        int $schoolYearId,
        array $organisations,
        ?string $parallel,
    ): Collection {
        return $this->database->transaction(function () use ($tenantId, $establishmentId, $schoolYearId, $organisations, $parallel): Collection {
            $classes = new Collection;

            foreach ($organisations as $organisation) {
                $cycle = RefCycle::query()
                    ->where('actif', true)
                    ->with(['niveaux' => fn ($query) => $query->orderBy('ordre')])
                    ->findOrFail($organisation['cycle_id']);
                $option = $organisation['option_id'] === null
                    ? null
                    : RefOption::query()->find($organisation['option_id']);

                if ($option !== null && (int) $option->cycle_id !== (int) $cycle->getKey()) {
                    throw ValidationException::withMessages([
                        'organisations' => ['Une option sélectionnée n’appartient pas au cycle indiqué.'],
                    ]);
                }

                if ($option === null && $cycle->options()->exists()) {
                    throw ValidationException::withMessages([
                        'organisations' => ['Sélectionnez au moins une option pour chaque cycle qui en organise.'],
                    ]);
                }

                foreach ($cycle->niveaux as $level) {
                    $template = $this->resolveClassTemplateService->resolve(
                        (int) $cycle->getKey(),
                        $option?->getKey(),
                        (int) $level->getKey(),
                    );
                    $name = $this->className($level->nom, $option?->nom, $parallel);
                    $code = $this->classCode($level->nom, $option?->nom, $parallel);

                    $class = Classe::query()->firstOrCreate(
                        [
                            'tenant_id' => $tenantId,
                            'etablissement_id' => $establishmentId,
                            'annee_scolaire_id' => $schoolYearId,
                            'cycle_id' => $cycle->getKey(),
                            'niveau_id' => $level->getKey(),
                            'option_id' => $option?->getKey(),
                            'parallele' => $parallel,
                        ],
                        [
                            'system_template_id' => $template->getKey(),
                            'nom' => $name,
                            'code' => $code,
                            'active' => true,
                        ],
                    );

                    $this->cloneSystemTemplateService->cloneForClasse($class);
                    $classes->push($class);
                }
            }

            return $classes->load(['cycle', 'niveau', 'option']);
        });
    }

    private function className(string $levelName, ?string $optionName, ?string $parallel): string
    {
        $normalizedLevelName = $optionName === null
            ? $levelName
            : Str::of($levelName)->replaceMatches('/\s+Humanités$/iu', '')->trim()->toString();

        return collect([$normalizedLevelName, $optionName, $parallel])
            ->filter(fn (?string $part): bool => filled($part))
            ->implode(' ');
    }

    private function classCode(string $levelName, ?string $optionName, ?string $parallel): string
    {
        $shortLevelName = Str::of($levelName)
            ->replaceMatches('/\s+Humanités$/iu', '')
            ->trim()
            ->toString();

        if ($optionName === null) {
            return Str::ascii(collect([$shortLevelName, $parallel])
                ->filter(fn (?string $part): bool => filled($part))
                ->implode(' '));
        }

        $ignoredWords = ['de', 'des', 'du', 'et', 'la', 'le', 'les'];
        $optionInitials = collect(preg_split('/[\s&\-]+/u', $optionName) ?: [])
            ->map(fn (string $word): string => Str::lower(trim($word)))
            ->reject(fn (string $word): bool => $word === '' || in_array($word, $ignoredWords, true))
            ->map(fn (string $word): string => Str::upper(Str::substr($word, 0, 1)))
            ->implode('');

        return Str::ascii(collect([$shortLevelName, $optionInitials, $parallel])
            ->filter(fn (?string $part): bool => filled($part))
            ->implode(' '));
    }
}
