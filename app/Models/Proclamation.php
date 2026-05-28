<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Model;

class Proclamation extends Model
{
    use HasTenant;

    protected $table = 'proclamations';
    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
    ];
}
