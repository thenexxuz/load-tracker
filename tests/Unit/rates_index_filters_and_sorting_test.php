<?php

it('keeps the rates index controller query wired for filters and name sorting', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $controller = file_get_contents($projectRoot.'/app/Http/Controllers/RateController.php');

    expect($controller)
        ->toContain('$sortBy = $request->input(\'sort_by\');')
        ->toContain('$status = $request->input(\'status\');')
        ->toContain('$type = $request->input(\'type\');')
        ->toContain('$carrierId = $request->input(\'carrier_id\');')
        ->toContain('if ($status === \'active\')')
        ->toContain('if ($status === \'inactive\')')
        ->toContain('if ($sortBy === \'name\')')
        ->toContain('->withQueryString()')
        ->toContain("'carrier_id' => \$carrierId")
        ->toContain("'sort_by' => \$sortBy")
        ->toContain("'sort_direction' => \$sortBy === 'name' ? \$sortDirection : null");
});

it('keeps the rates index page controls for type carrier status filters and sortable name', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $page = file_get_contents($projectRoot.'/resources/js/pages/Admin/Rates/Index.vue');

    expect($page)
        ->toContain('v-model="typeFilter"')
        ->toContain('v-model="carrierFilter"')
        ->toContain('v-model="statusFilter"')
        ->toContain('All types')
        ->toContain('All carriers')
        ->toContain('All statuses')
        ->toContain('Clear Filters')
        ->toContain('@click="toggleSort(\'name\')"')
        ->toContain("const sortIndicator = (column: 'name'): string =>")
        ->toContain('const clearFilters = () => {');
});
