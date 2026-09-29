<?php

declare(strict_types=1);

namespace CoreX\Operations\Authorization\Internal;

use CoreX\Operations\Authorization\Contracts\OperationFieldAuthorizer;
use CoreX\Operations\Definition\Operation;
use CoreX\Operations\Invocation\Contracts\RequestContext;
use Override;

/** @internal Intersects immutable field declarations with a host policy. */
final class DeclaredFieldAuthorizer implements OperationFieldAuthorizer
{
    #[Override]
    public function allowsRead(Operation $operation, RequestContext $context, string $path): bool
    {
        return self::declares($operation->readFields, $path);
    }

    #[Override]
    public function allowsWrite(Operation $operation, RequestContext $context, string $path): bool
    {
        return self::declares($operation->writeFields, $path);
    }

    /** @param array<string, mixed> $input */
    public static function firstForbiddenWrite(
        Operation $operation,
        RequestContext $context,
        OperationFieldAuthorizer $authorizer,
        array $input,
    ): ?string {
        foreach (self::leafPaths($input) as $path) {
            if (! self::declares($operation->writeFields, $path)
                || ! $authorizer->allowsWrite($operation, $context, $path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|list<mixed>  $data
     * @return array<string, mixed>|list<mixed>
     */
    public static function prune(
        Operation $operation,
        RequestContext $context,
        OperationFieldAuthorizer $authorizer,
        array $data,
    ): array {
        return self::pruneArray(
            operation: $operation,
            context: $context,
            authorizer: $authorizer,
            value: $data,
            pointer: '',
        );
    }

    /**
     * @param  array<string, mixed>|list<mixed>  $value
     * @return array<string, mixed>|list<mixed>
     */
    private static function pruneArray(
        Operation $operation,
        RequestContext $context,
        OperationFieldAuthorizer $authorizer,
        array $value,
        string $pointer,
    ): array {
        $result = [];
        $list = array_is_list($value);

        foreach ($value as $key => $item) {
            $path = $pointer.'/'.self::escape((string) $key);

            if (! self::covers($operation->readFields, $path)) {
                if ($list) {
                    $result[$key] = null;
                }

                continue;
            }

            if (is_array($item) && $item !== []) {
                $result[$key] = self::pruneArray($operation, $context, $authorizer, $item, $path);

                continue;
            }

            if (self::declares($operation->readFields, $path)
                && $authorizer->allowsRead($operation, $context, $path)) {
                $result[$key] = $item;
            } elseif ($list) {
                $result[$key] = null;
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>|list<mixed>  $value
     * @return list<string>
     */
    private static function leafPaths(array $value, string $pointer = ''): array
    {
        $paths = [];

        foreach ($value as $key => $item) {
            $path = $pointer.'/'.self::escape((string) $key);

            if (is_array($item) && $item !== []) {
                array_push($paths, ...self::leafPaths($item, $path));

                continue;
            }

            $paths[] = $path;
        }

        return $paths;
    }

    /** @param list<string>|null $declared */
    private static function declares(?array $declared, string $path): bool
    {
        if ($declared === null) {
            return true;
        }

        foreach ($declared as $field) {
            $pointer = self::pointer($field);

            if ($path === $pointer || str_starts_with($path, $pointer.'/')) {
                return true;
            }
        }

        return false;
    }

    /** @param list<string>|null $declared */
    private static function covers(?array $declared, string $path): bool
    {
        if (self::declares($declared, $path)) {
            return true;
        }

        foreach ($declared ?? [] as $field) {
            if (str_starts_with(self::pointer($field), $path.'/')) {
                return true;
            }
        }

        return false;
    }

    private static function pointer(string $field): string
    {
        return str_starts_with($field, '/') ? $field : '/'.self::escape($field);
    }

    private static function escape(string $segment): string
    {
        return str_replace(['~', '/'], ['~0', '~1'], $segment);
    }
}
