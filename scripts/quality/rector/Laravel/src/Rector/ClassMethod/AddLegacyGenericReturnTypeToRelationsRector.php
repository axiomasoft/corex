<?php

declare(strict_types=1);

namespace CoreX\Tooling\Rector\Laravel\Rector\ClassMethod;

use Rector\VersionBonding\ValueObject\ComposerPackageConstraint;

/**
 * Adds generics in the pre-11.15 format, using the child model class as the second generic instead of `$this`.
 */
final class AddLegacyGenericReturnTypeToRelationsRector extends AbstractAddGenericReturnTypeToRelationsRector
{
    /**
     * @return ComposerPackageConstraint
     */
    public function provideComposerPackageConstraint()
    {
        return new ComposerPackageConstraint('laravel/framework', '<11.15');
    }

    protected function shouldUseNewGenerics(): bool
    {
        return false;
    }

    protected function shouldUsePivotGeneric(): bool
    {
        return false;
    }
}
