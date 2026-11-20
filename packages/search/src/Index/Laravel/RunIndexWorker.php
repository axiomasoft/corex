<?php

declare(strict_types=1);

namespace CoreX\Search\Index\Laravel;

use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Data\Data\EntityHandle;
use CoreX\Search\Index\Contracts\IndexWorker;
use Illuminate\Console\Command;
use Packman\Attributes\Internal;

#[Internal]
final class RunIndexWorker extends Command
{
    protected $signature = 'corex:search:work {entity} {generation} {--limit=1}';

    protected $description = 'Advance one durable search generation in the current verified service scope';

    public function handle(TenantContextResolver $context, DataScopeFactory $scopes, IndexWorker $worker): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
        $generation = filter_var($this->argument('generation'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $name = $this->argument('entity');

        if ($generation === false || $limit === false) {
            return self::INVALID;
        }
        $scope = $scopes->forCurrentPrincipal($context->current());
        $entity = new EntityHandle($name);
        $count = 0;
        while ($count < $limit && $worker->advance($scope, $entity, $generation)) {
            $count++;
        }
        $this->line('Processed index phases: '.$count);

        return self::SUCCESS;
    }
}
