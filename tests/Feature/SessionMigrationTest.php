<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

test('the sessions table exists for database-backed sessions', function () {
    Artisan::call('migrate', [
        '--path' => 'database/migrations/2026_07_24_000001_create_sessions_table.php',
        '--force' => true,
    ]);

    expect(Schema::hasTable('sessions'))->toBeTrue();
});
