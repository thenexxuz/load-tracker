<?php

it('adds inbound and outbound location columns and backfills existing rows by type', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $migration = file_get_contents($projectRoot.'/database/migrations/2026_04_02_202822_add_inbound_and_outbound_to_locations_table.php');
    $model = file_get_contents($projectRoot.'/app/Models/Location.php');

    expect($migration)
        ->toContain("\$table->boolean('inbound')->default(false)->after('expected_arrival_time');")
        ->toContain("\$table->boolean('outbound')->default(false)->after('inbound');")
        ->toContain("->where('type', 'pickup')")
        ->toContain("'outbound' => true")
        ->toContain("->whereIn('type', ['distribution_center', 'recycling'])")
        ->toContain("'inbound' => true")
        ->toContain("\$table->dropColumn(['inbound', 'outbound']);");

    expect($model)
        ->toContain("'inbound',")
        ->toContain("'outbound',")
        ->toContain("'inbound' => 'boolean',")
        ->toContain("'outbound' => 'boolean',")
        ->toContain('static::saving(function (Location $location): void {')
        ->toContain('applyDirectionFlagsFromType();')
        ->toContain("\$this->inbound = in_array(\$this->type, ['distribution_center', 'recycling'], true);")
        ->toContain("\$this->outbound = \$this->type === 'pickup';");
});
