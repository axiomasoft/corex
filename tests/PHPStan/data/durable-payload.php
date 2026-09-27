<?php

declare(strict_types=1);

namespace CoreX\Tests\PHPStan\Data\DurablePayload;

use Closure;
use CoreX\Contracts\DurablePayload;
use CoreX\Tenancy\Contracts\TenantContext;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;

final class ValidPayload implements DurablePayload
{
    public string $name;

    public int $count;
}

final class InvalidModelPayload implements DurablePayload
{
    public Model $user;
}

final class InvalidClosurePayload implements DurablePayload
{
    public Closure $handler;
}

final class InvalidContainerPayload implements DurablePayload
{
    public Container $container;
}

final class InvalidTenantContextPayload implements DurablePayload
{
    public TenantContext $tenant;
}

final class InvalidUntypedArrayPayload implements DurablePayload
{
    public array $missing;
}

final class InvalidExplicitMixedArrayPayload implements DurablePayload
{
    /** @var array<mixed> */
    public array $explicit;
}

final class ValidTypedArrayPayload implements DurablePayload
{
    /** @var array<string, int> */
    public array $valid;
}

final class NotAPayload
{
    public Model $user;
}

final class PromotedPayload implements DurablePayload
{
    public function __construct(
        public TenantContext $ctx,
    ) {}
}

final class PromotedMissingArrayPayload implements DurablePayload
{
    public function __construct(
        public array $missing,
    ) {}
}

final class PromotedExplicitMixedArrayPayload implements DurablePayload
{
    public function __construct(
        /** @var array<mixed> */
        public array $explicit,
    ) {}
}

final class PromotedValidArrayPayload implements DurablePayload
{
    public function __construct(
        /** @var array<string, int> */
        public array $valid,
    ) {}
}
