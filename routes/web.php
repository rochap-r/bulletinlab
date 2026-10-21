<?php

use Illuminate\Support\Facades\Route;

// Route::view (et non une closure) pour rendre la route cacheable (route:cache en prod Docker).
Route::view('/', 'welcome');
