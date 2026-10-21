<?php

namespace App\Models;

use App\Enums\CalendarCycle;
use Illuminate\Database\Eloquent\Model;

class CalendrierPeriode extends Model
{
    protected $table = 'calendrier_periodes';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'cycle_scolaire' => CalendarCycle::class,
            'saisie_notes_ouverte' => 'boolean',
            'periode_active' => 'boolean',
        ];
    }
}
