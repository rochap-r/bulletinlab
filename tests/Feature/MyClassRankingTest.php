<?php

use Illuminate\Support\Facades\Route;

test('the titular ranking route is registered', function () {
    expect(Route::has('api.v1.me.classes.classements.show'))->toBeTrue();
});
