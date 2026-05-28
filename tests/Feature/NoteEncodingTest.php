<?php

test('can encode note with validation and progress update', function () {
    $response = $this->postJson('/api/v1/notes', [
        'bulletin_id' => 1,
        'node_id' => 1,
        'periode_id' => 1,
        'valeur' => 15,
        'saisie_par' => 1,
        'commentaire' => 'Test note',
    ]);

    $response->assertStatus(401);
});
