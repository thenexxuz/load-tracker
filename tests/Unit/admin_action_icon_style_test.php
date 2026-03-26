<?php

it('defines a shared action icon component that matches the carrier index edit and delete styles', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $component = file_get_contents($projectRoot.'/resources/js/components/ActionIconButton.vue');

    expect($component)
        ->toContain("action: 'edit' | 'delete'")
        ->toContain('text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-300 transition-colors')
        ->toContain('text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 transition-colors')
        ->toContain('M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z')
        ->toContain('M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16');
});

it('reuses the shared action icon component in admin views with edit and delete actions', function (): void {
    $projectRoot = dirname(__DIR__, 2);

    $files = [
        'resources/js/pages/Admin/Shipments/Index.vue',
        'resources/js/pages/Admin/Rates/Index.vue',
        'resources/js/pages/Admin/ScheduledItems/Index.vue',
        'resources/js/pages/Admin/Locations/Index.vue',
        'resources/js/pages/Admin/Shipments/Show.vue',
        'resources/js/components/NotesSection.vue',
    ];

    foreach ($files as $file) {
        $contents = file_get_contents($projectRoot.'/'.$file);

        expect($contents)
            ->toContain("import ActionIconButton from '@/components/ActionIconButton.vue'")
            ->toContain('<ActionIconButton');
    }
});
