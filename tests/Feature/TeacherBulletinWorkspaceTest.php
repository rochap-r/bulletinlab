<?php

test('the titular workspace routes are registered', function () {
    expect(route('api.v1.me.classes.eleves.bulletin.show', [11, 29], false))
        ->toBe('/api/v1/me/classes/11/eleves/29/bulletin')
        ->and(route('api.v1.me.classes.progression-notes.show', [11], false))
        ->toBe('/api/v1/me/classes/11/progression-notes');
});
