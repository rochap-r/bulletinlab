<?php

use App\Http\Controllers\Api\V1\Auth\MeController;
use Illuminate\Support\Facades\Route;

Route::get('me', MeController::class)->name('me.show');
