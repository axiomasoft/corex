<?php

declare(strict_types=1);

namespace CoreX\Operations\Authorization\Laravel;

use CoreX\Invocation\Contracts\RequestContext;
use CoreX\Operations\Authorization\Contracts\OperationFieldAuthorizer;
use CoreX\Operations\Definition\Data\Operation;
use Override;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class DeclaredFieldAuthorizer implements OperationFieldAuthorizer
{
    #[Override]
    public function allowsRead(Operation $operation, RequestContext $context, string $path): bool
    {
        return self::declares(declared: $operation->readFields, path: $path);
    }

    #[Override]
    public function allowsWrite(Operation $operation, RequestContext $context, string $path): bool
    {
        return self::declares(declared: $operation->writeFields, path: $path);
    }

    /** @param array<string, mixed> $input */
    public static function firstForbiddenWrite(
        Operation $operation,
        RequestContext $context,
        OperationFieldAuthorizer $authorizer,
        array $input,
    ): ?string {
        foreach (self::leafPaths($input) as $path) {
            if (! self::declares(declared: $operation->writeFields, path: $path)
                || ! $authorizer->allowsWrite(operation: $operation, context: $context, path: $path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
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
     * @param  array<array-key, mixed>  $value
     * @return array<array-key, mixed>
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

            if (! self::covers(declared: $operation->readFields, path: $path)) {
                if ($list) {
                    $result[$key] = null;
                }

                continue;
            }

            if (is_array($item) && $item !== []) {
                $result[$key] = self::pruneArray(operation: $operation, context: $context, authorizer: $authorizer, value: $item, pointer: $path);

                continue;
            }

            if (self::declares(declared: $operation->readFields, path: $path)
                && $authorizer->allowsRead(operation: $operation, context: $context, path: $path)) {
                $result[$key] = $item;
            } elseif ($list) {
                $result[$key] = null;
            }
        }

        return $result;
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return list<string>
     */
    private static function leafPaths(array $value, string $pointer = ''): array
    {
        $paths = [];

        foreach ($value as $key => $item) {
            $path = $pointer.'/'.self::escape((string) $key);

            if (is_array($item) && $item !== []) {
                array_push($paths, ...self::leafPaths(value: $item, pointer: $path));

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
        if (self::declares(declared: $declared, path: $path)) {
            return true;
        }

        return array_any($declared ?? [], fn ($field) => str_starts_with(self::pointer($field), $path.'/'));
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
