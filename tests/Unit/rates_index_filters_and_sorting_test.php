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
        ->toContain("'sort_direction' => \$sortBy === 'name' ? \$sortDirection : null")
        ->toContain('public function destroy(Request $request, Rate $rate)')
        ->toContain("'search',")
        ->toContain("'type',")
        ->toContain("'carrier_id',")
        ->toContain("'status',")
        ->toContain("'sort_by',")
        ->toContain("'sort_direction',")
        ->toContain("'per_page',")
        ->toContain("'page',");
});

it('keeps the rates index page controls for type carrier status filters and sortable name', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $page = file_get_contents($projectRoot.'/resources/js/pages/Admin/Rates/Index.vue');

    expect($page)
        ->toContain('const typeHeaderText = computed(() => {')
        ->toContain('const carrierHeaderText = computed(() => {')
        ->toContain('const statusHeaderText = computed(() => {')
        ->toContain('const toggleTypeFilter = () => {')
        ->toContain('const toggleCarrierFilter = () => {')
        ->toContain('const toggleStatusFilter = () => {')
        ->toContain('Teleport to="body"')
        ->toContain('ref="typeFilterRef"')
        ->toContain('ref="carrierFilterRef"')
        ->toContain('ref="statusFilterRef"')
        ->toContain('@click="toggleSort(\'name\')"')
        ->toContain('router.delete(route(\'admin.rates.destroy\', {')
        ->toContain('...currentParams.value,')
        ->toContain('page: props.rates.current_page,')
        ->toContain("const sortIndicator = (column: 'name'): string =>")
        ->toContain("return '▾'")
        ->toContain("return props.filters.sort_direction === 'desc' ? '▾' : '▴'")
        ->toContain('No end date');
});
