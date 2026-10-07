<?php

declare(strict_types=1);

namespace CoreX\Tooling\Rector\Laravel\Rector\ClassMethod;

use Rector\VersionBonding\ValueObject\ComposerPackageConstraint;

/**
 * Adds generics in the Laravel 12.3+ format, using `$this` as the child generic and the Pivot generic for
 * BelongsToMany relations.
 */
final class AddGenericReturnTypeToRelationsRector extends AbstractAddGenericReturnTypeToRelationsRector
{
    /**
     * @return ComposerPackageConstraint
     */
    public function provideComposerPackageConstraint()
    {
        return new ComposerPackageConstraint('laravel/framework', '>=12.3');
    }

    protected function shouldUseNewGenerics(): bool
    {
        return true;
    }

    protected function shouldUsePivotGeneric(): bool
    {
        return true;
    }
}
