<?php

test('the teacher class dashboard route is registered', function () {
    expect(route('api.v1.me.classes.index', absolute: false))->toBe('/api/v1/me/classes');
});
