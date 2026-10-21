<?php

namespace App\Models;

use App\Enums\CalendarCycle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendrierCycleParametre extends Model
{
    protected $table = 'calendrier_cycle_parametres';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'cycle_scolaire' => CalendarCycle::class,
            'jours_classe' => 'array',
            'jours_ouvrables_attendus' => 'integer',
            'heures_minimales' => 'integer',
        ];
    }

    /** @return BelongsTo<CalendrierScolaire, $this> */
    public function calendrier(): BelongsTo
    {
        return $this->belongsTo(CalendrierScolaire::class, 'calendrier_scolaire_id');
    }
}
