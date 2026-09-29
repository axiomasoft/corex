<?php

declare(strict_types=1);

namespace CoreX\Tests\Golden\Support;

use BackedEnum;
use Composer\Autoload\ClassLoader;
use CoreX\Messaging\Contracts\MessageContractRegistry;
use CoreX\Messaging\Events\DomainEvent;
use FilesystemIterator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionProperty;
use RuntimeException;
use UnitEnum;

final class GoldenSnapshot
{
    /** @return list<class-string> */
    public static function sourceClasses(string $repoRoot): array
    {
        // P01 includes historical classmap roots whose namespaces differ from their paths.
        // Read declarations, then let Composer load their actual names exactly once.
        $classes = [];
        $files = [];
        foreach (array_merge(glob($repoRoot.'/packages/*/src') ?: [], glob($repoRoot.'/products/*/src') ?: []) as $base) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }
        sort($files);
        foreach ($files as $file) {
            $source = file_get_contents($file);

            if ($source === false) {
                throw new RuntimeException("Cannot read source: {$file}");
            }
            $tokens = token_get_all($source, TOKEN_PARSE);
            $namespace = '';
            $depth = 0;
            $namespaceDepth = null;
            for ($i = 0, $count = count($tokens); $i < $count; $i++) {
                $token = $tokens[$i];

                if ($token === '{' || (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
                    $depth++;
                } elseif ($token === '}') {
                    if ($namespaceDepth === $depth) {
                        $namespace = '';
                        $namespaceDepth = null;
                    }
                    $depth--;
                } elseif (is_array($token) && $token[0] === T_NAMESPACE) {
                    $namespace = '';
                    while (++$i < $count) {
                        $part = $tokens[$i];

                        if ($part === ';' || $part === '{') {
                            if ($part === '{') {
                                $namespaceDepth = ++$depth;
                            }

                            break;
                        }

                        if (is_array($part) && in_array($part[0], [T_STRING, T_NAME_QUALIFIED, T_NS_SEPARATOR], true)) {
                            $namespace .= $part[1];
                        }
                    }
                } elseif (is_array($token) && in_array($token[0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)) {
                    $j = $i + 1;
                    while ($j < $count && is_array($tokens[$j]) && in_array($tokens[$j][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                        $j++;
                    }

                    // Anonymous classes and Foo::class have no following declaration name.
                    if ($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                        $class = ltrim($namespace.'\\'.$tokens[$j][1], '\\');

                        if (isset($classes[$class]) && $classes[$class] !== $file) {
                            throw new RuntimeException("Duplicate source type: {$class}");
                        }
                        $classes[$class] = $file;
                    }
                }
            }
        }
        // Secondary declarations in historical multi-class files need their declaring file.
        // This class map is process-local to Golden; production autoload remains tested separately.
        $loaders = ClassLoader::getRegisteredLoaders();
        $loader = reset($loaders);

        if ($loader === false) {
            throw new RuntimeException('Composer autoloader required');
        }
        $loader->addClassMap($classes);
        $classes = array_keys($classes);
        sort($classes);
        foreach ($classes as $class) {
            if (! class_exists($class) && ! interface_exists($class) && ! trait_exists($class) && ! enum_exists($class)) {
                throw new RuntimeException("Declared source type cannot autoload: {$class}");
            }
        }

        return $classes;
    }

    /** @return array<string, mixed> */
    public static function phpSurface(array $classes): array
    {
        $out = [];
        foreach ($classes as $class) {
            $r = new ReflectionClass($class);
            $constants = [];
            foreach ($r->getReflectionConstants() as $constant) {
                if (! $constant->isPublic() || $constant->getDeclaringClass()->getName() !== $class) {
                    continue;
                }
                $value = $constant->getValue();
                $constants[$constant->getName()] = [
                    'value' => self::surfaceValue($value),
                    'type' => get_debug_type($value),
                ];
            }
            ksort($constants);
            $properties = [];
            foreach ($r->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
                if ($property->getDeclaringClass()->getName() !== $class) {
                    continue;
                }
                $properties[$property->getName()] = [
                    'name' => $property->getName(),
                    'type' => (string) $property->getType(),
                    'static' => $property->isStatic(),
                    'readonly' => $property->isReadOnly(),
                ];
            }
            ksort($properties);
            $methods = [];
            foreach ($r->getMethods(ReflectionMethod::IS_PUBLIC) as $m) {
                if ($m->getDeclaringClass()->getName() !== $class) {
                    continue;
                }
                $methods[$m->getName()] = [
                    'static' => $m->isStatic(),
                    'return' => (string) $m->getReturnType(),
                    'params' => array_map(static function (ReflectionParameter $p): array {
                        $parameter = [
                            'name' => $p->getName(),
                            'type' => (string) $p->getType(),
                            'variadic' => $p->isVariadic(),
                            'byRef' => $p->isPassedByReference(),
                        ];

                        if ($p->isDefaultValueAvailable()) {
                            $parameter['default'] = self::surfaceValue($p->getDefaultValue());
                        }

                        return $parameter;
                    }, $m->getParameters()),
                ];
            }
            ksort($methods);
            $out[$class] = [
                'kind' => $r->isEnum() ? 'enum' : ($r->isInterface() ? 'interface' : ($r->isTrait() ? 'trait' : 'class')),
                'final' => $r->isFinal(),
                'abstract' => $r->isAbstract() && ! $r->isInterface(),
                'readonly' => $r->isReadOnly(),
                'internal' => str_contains($class, '\\Internal\\'),
                'parent' => $r->getParentClass() ? $r->getParentClass()->getName() : null,
                'interfaces' => self::sorted($r->getInterfaceNames()),
                'constants' => $constants,
                'properties' => $properties,
                'methods' => $methods,
            ];
        }

        return $out;
    }

    private static function surfaceValue(mixed $value): mixed
    {
        if ($value instanceof UnitEnum) {
            return ['enum' => $value::class, 'case' => $value->name]
                + ($value instanceof BackedEnum ? ['value' => $value->value] : []);
        }

        if (is_object($value)) {
            return ['class' => $value::class, 'properties' => self::surfaceValue(get_object_vars($value))];
        }

        if (is_array($value)) {
            return array_map(self::surfaceValue(...), $value);
        }

        return $value;
    }

    /** @return list<array<string, mixed>> */
    public static function routes(): array
    {
        $rows = [];
        foreach (Route::getRoutes() as $route) {
            $action = $route->getActionName();
            $rows[] = [
                'methods' => array_values(array_diff($route->methods(), ['HEAD'])),
                'uri' => $route->uri(),
                'name' => $route->getName(),
                'action' => $action === 'Closure' ? 'Closure' : $action,
            ];
        }
        usort($rows, static fn (array $a, array $b): int => [$a['uri'], $a['methods']] <=> [$b['uri'], $b['methods']]);

        return $rows;
    }

    /** @param list<class-string> $classes @return array<string, mixed> */
    public static function events(array $classes): array
    {
        $names = [];
        foreach ($classes as $class) {
            if (is_subclass_of($class, DomainEvent::class) && ! (new ReflectionClass($class))->isAbstract()) {
                $names[$class] = $class::name();
            }
        }
        ksort($names);

        $contracts = [];
        $registry = MessageContractRegistry::class;

        if (app()->bound($registry)) {
            foreach (app($registry)->all() as $contract) {
                $contracts[$contract->id()] = self::surfaceValue(get_object_vars($contract));
            }
        }
        ksort($contracts);

        return ['domain_events' => $names, 'message_contracts' => $contracts];
    }

    /** @return array<string, mixed> */
    public static function wire(string $tmp, string $fixture): array
    {
        if (file_exists($tmp)) {
            throw new RuntimeException("Snapshot directory already exists: {$tmp}");
        }

        try {
            if (Artisan::call('corex:operations:snapshot', ['directory' => $tmp, '--fixture' => $fixture]) !== 0) {
                throw new RuntimeException('Snapshot command failed: '.Artisan::output());
            }
            $out = [];
            foreach (['errors', 'operations', 'openapi', 'webhooks'] as $file) {
                $path = $tmp.'/'.$file.'.json';

                if (! is_file($path)) {
                    throw new RuntimeException("Missing snapshot: {$path}");
                }
                $out[$file] = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
            }

            return $out;
        } finally {
            foreach (glob($tmp.'/*') ?: [] as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }

            if (is_dir($tmp)) {
                rmdir($tmp);
            }
        }
    }

    /** @param list<string> $values @return list<string> */
    private static function sorted(array $values): array
    {
        sort($values);

        return array_values($values);
    }
}
