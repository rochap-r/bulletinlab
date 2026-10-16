<?php

namespace App\Domains\SchoolYear\Services;

use App\Models\RefNiveau;
use App\Models\RefOption;
use App\Models\SystemTemplateBulletin;
use Illuminate\Validation\ValidationException;

/**
 * Lie une classe au modèle de bulletin configuré côté plateforme :
 * même cycle, même option (ou générique), niveau dans la plage du modèle.
 * En cas d'ambiguïté, le modèle à la plage la plus étroite gagne.
 */
final class ResolveClassTemplateService
{
    public function resolve(int $cycleId, ?int $optionId, int $niveauId): SystemTemplateBulletin
    {
        $niveau = RefNiveau::query()->find($niveauId);

        if ($niveau === null) {
            throw ValidationException::withMessages([
                'niveau_id' => ['Le niveau sélectionné est introuvable.'],
            ]);
        }

        if ((int) $niveau->cycle_id !== $cycleId) {
            throw ValidationException::withMessages([
                'niveau_id' => ['Le niveau sélectionné n’appartient pas à ce cycle.'],
            ]);
        }

        if ($optionId !== null) {
            $option = RefOption::query()->find($optionId);

            if ($option === null || (int) $option->cycle_id !== $cycleId) {
                throw ValidationException::withMessages([
                    'option_id' => ['L’option sélectionnée n’est pas organisée dans ce cycle.'],
                ]);
            }
        } elseif (RefOption::query()->where('cycle_id', $cycleId)->exists()) {
            throw ValidationException::withMessages([
                'option_id' => ['Ce cycle organise des options : sélectionnez-en une.'],
            ]);
        }

        $candidates = SystemTemplateBulletin::query()
            ->where('actif', true)
            ->where('cycle_id', $cycleId)
            ->when(
                $optionId === null,
                fn ($query) => $query->whereNull('option_id'),
                fn ($query) => $query->where('option_id', $optionId),
            )
            ->with(['niveauMin:id,numero', 'niveauMax:id,numero'])
            ->get()
            ->filter(fn (SystemTemplateBulletin $template): bool => $this->covers($template, (int) $niveau->numero));

        if ($candidates->isEmpty()) {
            throw ValidationException::withMessages([
                'system_template_id' => ['Aucun modèle de bulletin configuré côté plateforme pour ce niveau et cette option.'],
            ]);
        }

        return $candidates
            ->sortBy([
                fn (SystemTemplateBulletin $template): int => $this->rangeWidth($template),
                fn (SystemTemplateBulletin $template): int => $template->getKey(),
            ])
            ->first();
    }

    private function covers(SystemTemplateBulletin $template, int $numero): bool
    {
        $min = $template->niveauMin?->numero;
        $max = $template->niveauMax?->numero;

        return ($min === null || $numero >= $min) && ($max === null || $numero <= $max);
    }

    private function rangeWidth(SystemTemplateBulletin $template): int
    {
        $min = $template->niveauMin?->numero;
        $max = $template->niveauMax?->numero;

        if ($min === null || $max === null) {
            return PHP_INT_MAX;
        }

        return $max - $min;
    }
}
