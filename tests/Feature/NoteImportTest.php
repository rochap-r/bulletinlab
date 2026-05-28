<?php

use Illuminate\Http\UploadedFile;

test('can import notes from file', function () {
    $file = UploadedFile::fake()->create('notes.csv', 100);

    $response = $this->postJson('/api/v1/notes/import', [
        'file' => $file,
        'classe_id' => 1,
        'node_id' => 1,
        'periode_id' => 1,
    ]);

    $response->assertStatus(401);
});
