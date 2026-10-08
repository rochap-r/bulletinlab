<?php

test('can lock period and block encoding', function () {
    $response = $this->postJson('/api/v1/classes/1/periodes/1/verrouillages', [
        'motif' => 'Test verrouillage',
    ]);

    $response->assertStatus(401);
});
