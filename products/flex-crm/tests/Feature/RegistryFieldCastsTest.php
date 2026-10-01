<?php

declare(strict_types=1);

use CoreX\Intl\IntlServiceProvider;
use CoreX\Modules\Data\Entity;
use CoreX\Modules\Data\ModuleManifest;
use CoreX\Modules\Internal\Compiler\RegistryCompiler;
use Flex\Crm\Tests\RegistryMoneyModel;

it('compiles a consumer money field using the intl cast registration', function (): void {
    $this->app->register(IntlServiceProvider::class);
    $manifest = ModuleManifest::make('example/money-consumer')->title('Money')->tablePrefix('mon')
        ->extends([Entity::make(RegistryMoneyModel::class)->handle('mon.money')->table('mon_money')]);

    $registry = app(RegistryCompiler::class)->compile([$manifest], installedPackages: []);
    $field = collect($registry->entities['mon.money']->fields)->firstWhere('handle', 'amount');

    expect($field->type)->toBe('money');
});
