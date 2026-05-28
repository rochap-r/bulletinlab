<?php

test('can get students with period and subject filters', function () {
    $response = $this->getJson('/api/v1/classes/1/students?periode_id=1&matiere_id=1');

    $response->assertStatus(401);
});
