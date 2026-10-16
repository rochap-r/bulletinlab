<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $this->renameGeneratedClasses(false);
    }

    public function down(): void
    {
        $this->renameGeneratedClasses(true);
    }

    private function renameGeneratedClasses(bool $restoreHumanites): void
    {
        DB::table('classes')
            ->join('ref_niveaux', 'ref_niveaux.id', '=', 'classes.niveau_id')
            ->join('ref_options', 'ref_options.id', '=', 'classes.option_id')
            ->select([
                'classes.id',
                'classes.nom',
                'classes.code',
                'classes.parallele',
                'ref_niveaux.nom as niveau_nom',
                'ref_options.nom as option_nom',
            ])
            ->orderBy('classes.id')
            ->each(function (object $class) use ($restoreHumanites): void {
                $shortLevelName = Str::of($class->niveau_nom)
                    ->replaceMatches('/\s+Humanités$/iu', '')
                    ->trim()
                    ->toString();
                $parallel = filled($class->parallele) ? ' '.$class->parallele : '';
                $legacyName = "{$class->niveau_nom} {$class->option_nom}{$parallel}";
                $normalizedName = "{$shortLevelName} {$class->option_nom}{$parallel}";
                $expectedName = $restoreHumanites ? $normalizedName : $legacyName;
                $replacementName = $restoreHumanites ? $legacyName : $normalizedName;
                $generatedCode = $this->generatedCode($shortLevelName, $class->option_nom, $class->parallele);
                $hasGeneratedName = in_array($class->nom, [$legacyName, $normalizedName], true);
                $updates = [];

                if ($class->nom === $expectedName && $expectedName !== $replacementName) {
                    $updates['nom'] = $replacementName;
                }

                if ($hasGeneratedName && ! $restoreHumanites && blank($class->code)) {
                    $updates['code'] = $generatedCode;
                } elseif ($hasGeneratedName && $restoreHumanites && $class->code === $generatedCode) {
                    $updates['code'] = null;
                }

                if ($updates !== []) {
                    DB::table('classes')->where('id', $class->id)->update($updates);
                }
            });
    }

    private function generatedCode(string $levelName, string $optionName, ?string $parallel): string
    {
        $ignoredWords = ['de', 'des', 'du', 'et', 'la', 'le', 'les'];
        $optionInitials = collect(preg_split('/[\s&\-]+/u', $optionName) ?: [])
            ->map(fn (string $word): string => Str::lower(trim($word)))
            ->reject(fn (string $word): bool => $word === '' || in_array($word, $ignoredWords, true))
            ->map(fn (string $word): string => Str::upper(Str::substr($word, 0, 1)))
            ->implode('');

        return collect([$levelName, $optionInitials, $parallel])
            ->filter(fn (?string $part): bool => filled($part))
            ->implode(' ');
    }
};
