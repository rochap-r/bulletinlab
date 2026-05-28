<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Model;

class Etablissement extends Model
{
    use HasTenant;

    protected $table = 'etablissements';
    protected $guarded = [];
}
