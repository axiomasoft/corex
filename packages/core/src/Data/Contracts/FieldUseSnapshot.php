<?php

declare(strict_types=1);

namespace CoreX\Data\Contracts;

use Closure;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\EntityHandle;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '2.0.0')]
interface FieldUseSnapshot extends FieldUsePolicy
{
    /**
     * Run the read with all plans for this scope and entity evaluated against one fresh owner snapshot.
     * Return null without invoking the read when the policy cannot provide that guarantee.
     *
     * @template T
     *
     * @param  Closure(): T  $read
     * @return T|null
     */
    public function inSnapshot(DataScope $scope, EntityHandle $entity, Closure $read): mixed;
}
