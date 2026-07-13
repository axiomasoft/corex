<?php

declare(strict_types=1);

namespace Flex\Modules\Exceptions;

use RuntimeException;

/**
 * Compilation validation failure (B-10 §7.4 п.4): always a deploy failure,
 * never a warning.
 */
final class CompilationException extends RuntimeException
{
    public static function duplicateTablePrefix(string $prefix, string $firstModule, string $secondModule): self
    {
        return new self(sprintf(
            'Table prefix [%s] is claimed by both [%s] and [%s] — tablePrefix must be unique across the deployment (D9).',
            $prefix,
            $firstModule,
            $secondModule,
        ));
    }

    public static function unmetRequirement(string $composerName, string $missingDependency): self
    {
        return new self(sprintf(
            'Module [%s] requires [%s], which is not installed in this deployment (requires must mirror composer, B-10 AC-2).',
            $composerName,
            $missingDependency,
        ));
    }

    public static function unknownExtender(string $composerName, string $class): self
    {
        return new self(sprintf(
            'Module [%s] declares extender [%s], which is outside the closed set of 8 Flex\\Modules\\Extend\\* classes. '
            .'New extenders require an RFC in flex/modules (docs/rfcs/NNNN-*.md, B-10 §4.3).',
            $composerName,
            $class,
        ));
    }

    public static function unknownEntityHandle(string $composerName, string $entityHandle): self
    {
        return new self(sprintf(
            'Module [%s] declares an EntityField extender targeting entity handle [%s], which no compiled manifest registers.',
            $composerName,
            $entityHandle,
        ));
    }

    public static function tablePrefixMismatch(string $composerName, string $table, string $expectedPrefix): self
    {
        return new self(sprintf(
            'Module [%s] declares Entity table [%s], which does not start with its own table prefix [%s_] (D9).',
            $composerName,
            $table,
            $expectedPrefix,
        ));
    }
}
