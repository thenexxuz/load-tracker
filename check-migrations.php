<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->boot();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

echo "=== Database Migration Check ===\n\n";

// Check if trailers table exists
if (Schema::hasTable('trailers')) {
    echo "✓ Trailers table EXISTS\n";
    echo "  Columns: " . implode(', ', Schema::getColumnListing('trailers')) . "\n";
} else {
    echo "✗ Trailers table MISSING\n";
}

echo "\n=== Shipments Table ===\n";
$shipmentColumns = Schema::getColumnListing('shipments');
echo "Total columns: " . count($shipmentColumns) . "\n";

// Check specific columns
$requiredColumns = ['trailer_id', 'loaned_from_trailer_id'];
foreach ($requiredColumns as $col) {
    if (in_array($col, $shipmentColumns)) {
        echo "✓ Column '$col' exists\n";
    } else {
        echo "✗ Column '$col' MISSING\n";
    }
}

echo "\n=== Pending Migrations ===\n";
$pending = DB::table('migrations')
    ->where('migration', 'like', '%trailer%')
    ->get();

if ($pending->count() === 0) {
    echo "No trailer-related migrations found in migrations table\n";
} else {
    foreach ($pending as $migration) {
        echo "- {$migration->migration} (batch: {$migration->batch})\n";
    }
}

echo "\n";
