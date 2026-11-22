<?php

declare(strict_types=1);

namespace CoreX\Context\Laravel;

use CoreX\Context\Exceptions\ScopeContextMismatch;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Call, since: '2.0.0')]
final class WorkQueueHandlers
{
    /** @var array<string, string> */
    private array $modes = [];

    public function __construct(ScopeOptions $options)
    {
        foreach (['tenant' => $options->tenantJobs, 'central' => $options->centralJobs] as $mode => $jobs) {
            foreach ($jobs as $handler) {
                $this->declare(handler: $handler, mode: $mode);
            }
        }
    }

    public function tenant(string $handler): void
    {
        $this->declare(handler: $handler, mode: 'tenant');
    }

    public function central(string $handler): void
    {
        $this->declare(handler: $handler, mode: 'central');
    }

    public function mode(string $handler): string
    {
        return $this->modes[$handler] ?? throw new ScopeContextMismatch('Undeclared work handler.');
    }

    private function declare(string $handler, string $mode): void
    {
        if ($handler === '' || $handler === 'Illuminate\\Queue\\CallQueuedHandler@call' || (isset($this->modes[$handler]) && $this->modes[$handler] !== $mode)) {
            throw new ScopeContextMismatch('Invalid work handler declaration.');
        }
        $this->modes[$handler] = $mode;
    }
}
