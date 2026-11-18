<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Internal\Laravel;

use CoreX\CustomFields\Internal\Integration\AttributeMessageContractRegistry;
use CoreX\CustomFields\Internal\Integration\AttributeMessageTranslator;
use CoreX\Data\Contracts\DataScopeCodec;
use CoreX\Messaging\Contracts\MessageContractRegistry;
use Illuminate\Contracts\Foundation\Application;
use Override;
use Packman\Attributes\Internal;
use Packman\Foundation\Contracts\PackageRegistrar;

/** @internal Composition of the package's message contract and translator. */
#[Internal]
final class AttributeMessagingRegistrar implements PackageRegistrar
{
    #[Override]
    public function register(Application $app): void
    {
        $app->extend(
            MessageContractRegistry::class,
            static fn (MessageContractRegistry $registry): AttributeMessageContractRegistry => new AttributeMessageContractRegistry($registry),
        );
        $app->singleton(
            AttributeMessageTranslator::class,
            static fn (Application $app): AttributeMessageTranslator => new AttributeMessageTranslator(
                scopeCodec: $app->make(DataScopeCodec::class),
            ),
        );
    }

    #[Override]
    public function boot(Application $app): void {}
}
