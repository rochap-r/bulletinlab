<?php

test('contextual note encoding requires authentication', function () {
    $response = $this->putJson(
        '/api/v1/me/attributions/1/creneaux/1/inscriptions/1/note',
        [
            'valeur' => 15,
            'commentaire' => 'Test note',
        ],
    );

    $response->assertUnauthorized();
});
