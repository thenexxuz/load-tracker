<?php

it('keeps registration auto-assigning supervisor for pegasus logistics emails', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $action = file_get_contents($projectRoot.'/app/Actions/Fortify/CreateNewUser.php');

    expect($action)
        ->toContain("if (\$this->shouldAssignSupervisorRole(\$input['email'])) {")
        ->toContain("Role::findOrCreate('supervisor', 'web');")
        ->toContain("\$user->assignRole('supervisor');")
        ->toContain("return str_ends_with(strtolower(trim(\$email)), '@pegasuslogistics.com');");
});
