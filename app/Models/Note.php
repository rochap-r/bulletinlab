<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Model;

class Note extends Model
{
    use HasTenant;

    protected $table = 'notes';
    protected $guarded = [];
}
