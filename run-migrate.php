<?php

require __DIR__ . '/vendor/autoload.php';

// Create app instance
$app = require_once __DIR__ . '/bootstrap/app.php';

// Run migrations
$exit_code = $app->make(\Illuminate\Contracts\Console\Kernel::class)
    ->call('migrate', ['--force' => true]);

echo "Migration complete. Exit code: {$exit_code}\n";
exit($exit_code);
