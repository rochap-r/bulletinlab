<?php

test('can get class enrollments', function () {
    $response = $this->getJson('/api/v1/classes/1/inscriptions');

    $response->assertStatus(401);
});
