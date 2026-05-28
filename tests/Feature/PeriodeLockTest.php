<?php

test('can lock period and block encoding', function () {
    $response = $this->postJson('/api/v1/periods/1/lock', [
        'classe_id' => 1,
        'motif' => 'Test verrouillage',
    ]);

    $response->assertStatus(401);
});
