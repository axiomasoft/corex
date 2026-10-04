<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Tests\Support\Engine;

use Closure;
use CoreX\Context\Data\DatabaseConnectionConfig;
use CoreX\Context\Data\TenantContext;
use CoreX\Tenancy\Runtime\Contracts\TenantBootstrapper;
use Override;
use Throwable;

/** A bootstrapper that logs its calls and can be told to fail. */
final class SpyBootstrapper implements TenantBootstrapper
{
    public ?Closure $onBootstrap = null;

    public ?Throwable $failBootstrap = null;

    public ?Throwable $failRevert = null;

    public function __construct(private readonly string $name, private readonly EngineLog $log) {}

    #[Override]
    public function bootstrap(TenantContext $context, DatabaseConnectionConfig $database): void
    {
        $this->log->entries[] = "bootstrap:{$this->name}:{$context->account->id}";

        if ($this->onBootstrap !== null) {
            ($this->onBootstrap)();
        }

        if ($this->failBootstrap !== null) {
            throw $this->failBootstrap;
        }
    }

    #[Override]
    public function revert(): void
    {
        $this->log->entries[] = "revert:{$this->name}";

        if ($this->failRevert !== null) {
            throw $this->failRevert;
        }
    }
}
