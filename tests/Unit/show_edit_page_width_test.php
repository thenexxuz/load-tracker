<?php

it('keeps admin show and edit pages free of page-level max width constraints', function (): void {
    $projectRoot = dirname(__DIR__, 2);

    $pages = [
        'resources/js/pages/Admin/ScheduledItems/Show.vue',
        'resources/js/pages/Admin/ScheduledItems/Edit.vue',
        'resources/js/pages/Admin/Locations/Show.vue',
        'resources/js/pages/Admin/Locations/Edit.vue',
        'resources/js/pages/Admin/Templates/Show.vue',
        'resources/js/pages/Admin/Templates/Edit.vue',
        'resources/js/pages/Admin/Shipments/Edit.vue',
        'resources/js/pages/Admin/Rates/Show.vue',
        'resources/js/pages/Admin/Rates/Edit.vue',
        'resources/js/pages/Admin/Carriers/Edit.vue',
    ];

    foreach ($pages as $page) {
        expect(file_get_contents($projectRoot.'/'.$page))
            ->not->toContain('max-w-2xl')
            ->not->toContain('max-w-3xl')
            ->not->toContain('max-w-4xl mx-auto')
            ->not->toContain('p-6 max-w-4xl')
            ->not->toContain('p-6 max-w-4xl mx-auto');
    }
});
