<?php

namespace App\Models;

use App\Enums\CalendarAlertLevel;
use App\Enums\CalendarCycle;
use App\Enums\CalendarEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendrierEvenement extends Model
{
    protected $table = 'calendrier_evenements';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type_evenement' => CalendarEventType::class,
            'cycle_scolaire' => CalendarCycle::class,
            'niveau_alerte' => CalendarAlertLevel::class,
            'date_debut' => 'date:Y-m-d',
            'date_fin' => 'date:Y-m-d',
            'audiences' => 'array',
            'delai_alerte_jours' => 'integer',
        ];
    }

    /** @return BelongsTo<CalendrierScolaire, $this> */
    public function calendrier(): BelongsTo
    {
        return $this->belongsTo(CalendrierScolaire::class, 'calendrier_scolaire_id');
    }
}
