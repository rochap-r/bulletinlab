<?php

return [
    'default_password' => env('RESPONSABLE_DEFAULT_PASSWORD', '123456'),
    'session_minutes' => (int) env('RESPONSABLE_SESSION_MINUTES', 720),
    'alerts' => [
        'max_children' => (int) env('RESPONSABLE_ALERT_MAX_CHILDREN', 50),
        'max_schools' => (int) env('RESPONSABLE_ALERT_MAX_SCHOOLS', 10),
    ],
];
