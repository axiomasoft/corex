<?php

declare(strict_types=1);

namespace CoreX\Modules\Internal\Compiler;

use CoreX\Modules\Contracts\FieldCastRegistry;

/** @internal Mutable composition registry. */
final class FieldCastMap implements FieldCastRegistry
{
    /** @var array<class-string, 'string'|'int'|'decimal'|'bool'|'date'|'datetime'|'json'|'money'|'relation'|'enum'> */
    private array $types = [];

    public function register(string $castClass, string $type): void
    {
        $this->types[$castClass] = $type;
    }

    public function typeFor(string $cast): ?string
    {
        $class = strstr($cast, ':', true);

        return $this->types[$class === false ? $cast : $class] ?? null;
    }
}
