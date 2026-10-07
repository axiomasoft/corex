<?php

declare(strict_types=1);

namespace CoreX\Tooling\Rector\Laravel\Rector\ClassMethod;

use Rector\VersionBonding\ValueObject\ComposerPackageConstraint;

/**
 * Adds generics in the Laravel 11.15+ format, using `$this` as the child generic, but without the Pivot generic
 * that BelongsToMany relations gained in 12.3.
 */
final class AddNewGenericReturnTypeToRelationsRector extends AbstractAddGenericReturnTypeToRelationsRector
{
    /**
     * @return ComposerPackageConstraint
     */
    public function provideComposerPackageConstraint()
    {
        return new ComposerPackageConstraint('laravel/framework', '>=11.15 <12.3');
    }

    protected function shouldUseNewGenerics(): bool
    {
        return true;
    }

    protected function shouldUsePivotGeneric(): bool
    {
        return false;
    }
}
