<?php

it('serializes user index roles as display-ready strings for the users management page', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $controller = file_get_contents($projectRoot.'/app/Http/Controllers/UserController.php');
    $page = file_get_contents($projectRoot.'/resources/js/pages/Admin/Users/Index.vue');

    expect($controller)
        ->toContain("User::with(['roles', 'carrier:id,name'])")
        ->toContain("->map(fn (string \$roleName) => \$roleName === 'carrier' && \$user->carrier")
        ->toContain('->pluck(\'name\')')
        ->toContain('{$user->carrier->name}')
        ->toContain(': $roleName)');

    expect($page)
        ->toContain('roles: string[]')
        ->toContain("{{ user.roles.join(', ') || 'None' }}");
});
