<?php

declare(strict_types=1);

namespace CoreX\Attributes\Contracts;

interface AttributeStoreResolver
{
    public function forEntity(string $entity): AttributeStore;
}
