<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BulletinBlocValue extends Model
{
    protected $table = 'bulletin_bloc_values';
    protected $guarded = [];

    protected $casts = [
        'data' => 'array',
    ];
}
