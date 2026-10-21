<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\CalendarCycle;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SchoolCalendarIndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'cycle' => ['nullable', Rule::enum(CalendarCycle::class)->except(CalendarCycle::Tous)],
        ];
    }
}
