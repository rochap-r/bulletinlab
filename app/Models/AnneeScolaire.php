<?php

namespace App\Models;

use App\Domains\SchoolYear\Exceptions\SchoolYearException;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnneeScolaire extends Model
{
    public const START_MONTH = 9;

    protected $table = 'annees_scolaires';

    protected $fillable = [
        'calendrier_scolaire_id',
        'libelle',
        'date_debut',
        'date_fin',
        'active',
        'cloturee',
    ];

    protected $casts = [
        'active' => 'boolean',
        'cloturee' => 'boolean',
    ];

    public function calendrierScolaire(): BelongsTo
    {
        return $this->belongsTo(CalendrierScolaire::class, 'calendrier_scolaire_id');
    }

    public static function libellePourDate(CarbonInterface|string $date): string
    {
        if (! $date instanceof CarbonInterface) {
            $date = CarbonImmutable::parse($date);
        }
        $startYear = (int) $date->format('Y');
        if ((int) $date->format('n') < self::START_MONTH) {
            $startYear--;
        }

        return sprintf('%d-%d', $startYear, $startYear + 1);
    }

    public static function forDate(?CarbonInterface $date = null): ?self
    {
        $date ??= CarbonImmutable::now(config('app.business_timezone'));

        $matches = self::query()
            ->where('active', true)
            ->where('cloturee', false)
            ->where('date_debut', '<=', $date->toDateString())
            ->where('date_fin', '>=', $date->toDateString())
            ->orderBy('id')
            ->limit(2)
            ->get();

        if ($matches->count() > 1) {
            throw new SchoolYearException(
                'Multiple active school years overlap on the same date.',
            );
        }

        return $matches->first();
    }
}
