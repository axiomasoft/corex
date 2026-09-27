<?php

declare(strict_types=1);

namespace CoreX\Attributes\Internal\Laravel;

use CoreX\Attributes\Internal\Integration\AttributeMessageContractRegistry;
use CoreX\Attributes\Internal\Integration\AttributeMessageTranslator;
use CoreX\Contracts\DataScopeCodec;
use CoreX\Messaging\Contracts\MessageContractRegistry;
use Illuminate\Contracts\Foundation\Application;

/** @internal Composition of the package's message contract and translator. */
final class AttributeMessagingRegistrar
{
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
}
