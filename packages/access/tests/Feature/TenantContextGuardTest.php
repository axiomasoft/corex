<?php

declare(strict_types=1);

use AzGuard\Contracts\ContextGuard;
use CoreX\Access\Tests\Stubs\ContextUser;
use CoreX\Access\Tests\Stubs\Project;
use CoreX\Modules\Data\EntityDefinition;
use CoreX\Modules\Testing\ArrayEntityRegistry;

beforeEach(function (): void {
    app(ArrayEntityRegistry::class)->register(new EntityDefinition(
        handle: 'project',
        model: Project::class,
        table: 'projects',
        module: 'corex/access-tests',
        labelSingular: 'Project',
        labelPlural: 'Projects',
        capabilities: [],
        fields: [],
        titleAttribute: 'name',
    ));
    $this->guard = app(ContextGuard::class);
});

it('returns false when the context entity does not exist', function (): void {
    $user = new ContextUser(scoped: true, global: true);

    expect($this->guard->checkInContext($user, 'project', 'missing', 'crm.deal.view', 'app'))->toBeFalse();
});

it('returns false for an unknown context type', function (): void {
    $user = new ContextUser(scoped: true, global: true);

    expect($this->guard->checkInContext($user, 'unknown', '1', 'crm.deal.view', 'app'))->toBeFalse();
});

it('grants when the user has the permission scoped to the entity', function (): void {
    $project = Project::create(['name' => 'p']);
    $user = new ContextUser(scoped: true);

    expect($this->guard->checkInContext($user, 'project', $project->id, 'crm.deal.view', 'app'))->toBeTrue();
});

it('falls back to a global permission within the panel', function (): void {
    $project = Project::create(['name' => 'p']);
    $user = new ContextUser(global: true);

    expect($this->guard->checkInContext($user, 'project', $project->id, 'crm.deal.view', 'app'))->toBeTrue();
});

it('denies when the user has neither scoped nor global permission', function (): void {
    $project = Project::create(['name' => 'p']);
    $user = new ContextUser;

    expect($this->guard->checkInContext($user, 'project', $project->id, 'crm.deal.view', 'app'))->toBeFalse();
});
