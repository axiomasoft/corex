<?php

declare(strict_types=1);

namespace CoreX\Tests\Support;

use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
use RuntimeException;
use Stringable;

/**
 * P4.12 — scan package ServiceProviders and Registrars for singleton ::class literals and flag
 * constructors that capture Application, Request, or Config Repository (L1).
 * P05.3 (D70 п.11): L2 — a singleton constructor takes a type registered as scoped
 * (captive dependency); L3 — a singleton constructor takes a database connection.
 */
final class SingletonInjectionGate
{
    /** @var list<string> */
    public const FORBIDDEN_TYPES = [
        'Illuminate\Contracts\Foundation\Application',
        'Illuminate\Http\Request',
        'Illuminate\Contracts\Config\Repository',
    ];

    /** @var list<string> */
    public const CONNECTION_TYPES = [
        'Illuminate\Database\Connection',
        'Illuminate\Database\ConnectionInterface',
    ];

    /**
     * @return list<array{
     *     package: string,
     *     provider: string,
     *     abstract: string,
     *     lifetime: string,
     *     concrete_fqcn: ?string,
     *     static_parse: bool,
     *     captures_context: bool,
     *     verdict: string,
     *     violation: ?string,
     *     rule: ?string,
     *     type: ?string,
     * }>
     */
    public static function auditRows(): array
    {
        $rows = [];
        $scoped = self::scopedAbstracts();

        foreach (self::serviceProviderFiles() as $providerPath) {
            $package = self::packageFromProviderPath($providerPath);
            $content = (string) file_get_contents($providerPath);
            $useMap = self::parseUseMap($content);
            $namespace = self::parseNamespace($content);

            foreach (self::extractSingletonRegistrations($content) as $registration) {
                $abstractFqcn = self::resolveClassName($registration['abstract'], $namespace, $useMap);
                $staticParse = $registration['concrete_literal'];

                if ($staticParse) {
                    $concreteFqcn = $registration['concrete'] !== null
                        ? self::resolveClassName($registration['concrete'], $namespace, $useMap)
                        : $abstractFqcn;
                    $concretes = [$concreteFqcn];
                } else {
                    // Factory closure: every class the closure constructs lives as long as the singleton.
                    $concretes = array_values(array_filter(array_map(
                        static fn (string $name): ?string => self::resolveTypeName($name, $namespace, $useMap),
                        $registration['constructed'],
                    ), static fn (?string $resolved): bool => $resolved !== null));
                    $staticParse = $concretes !== [];
                    $concretes = $concretes === [] ? [null] : $concretes;
                }

                foreach ($concretes as $concreteFqcn) {
                    $found = $concreteFqcn !== null && $staticParse && self::classInMonorepoSrc($concreteFqcn)
                        ? self::constructorViolation($concreteFqcn, $scoped)
                        : null;
                    $verdict = match (true) {
                        ! $staticParse => 'static-limit',
                        $found !== null => 'allowlist-required',
                        $concreteFqcn !== null && self::classInMonorepoSrc($concreteFqcn) => 'stateless-ok',
                        default => 'ok',
                    };

                    $rows[] = [
                        'package' => $package,
                        'provider' => self::relativePath($providerPath),
                        'abstract' => $abstractFqcn ?? $registration['abstract'],
                        'lifetime' => 'singleton',
                        'concrete_fqcn' => $concreteFqcn,
                        'static_parse' => $staticParse,
                        'captures_context' => $found !== null,
                        'verdict' => $verdict,
                        'violation' => $found['parameter'] ?? null,
                        'rule' => $found['rule'] ?? null,
                        'type' => $found['type'] ?? null,
                    ];
                }
            }

            foreach (self::extractScopedRegistrations($content) as $registration) {
                $abstractFqcn = self::resolveClassName($registration['abstract'], $namespace, $useMap);

                $rows[] = [
                    'package' => $package,
                    'provider' => self::relativePath($providerPath),
                    'abstract' => $abstractFqcn ?? $registration['abstract'],
                    'lifetime' => 'scoped',
                    'concrete_fqcn' => null,
                    'static_parse' => $registration['concrete_literal'],
                    'captures_context' => false,
                    'verdict' => $registration['concrete_literal'] ? 'ok (scoped)' : 'static-limit',
                    'violation' => null,
                    'rule' => null,
                    'type' => null,
                ];
            }
        }

        usort($rows, static fn (array $a, array $b): int => [$a['package'], $a['abstract'], $a['lifetime']]
            <=> [$b['package'], $b['abstract'], $b['lifetime']]);

        return $rows;
    }

    /**
     * @return list<string> repo-relative violation lines for non-allowlisted hits
     */
    public static function violations(string $allowlistPath): array
    {
        $allowlist = self::loadAllowlist($allowlistPath);
        $violations = [];

        foreach (self::auditRows() as $row) {
            if (! $row['captures_context'] || $row['violation'] === null || $row['concrete_fqcn'] === null) {
                continue;
            }

            $key = self::allowlistKey($row['package'], $row['concrete_fqcn'], $row['violation']);

            if (isset($allowlist[$key])) {
                continue;
            }

            $violations[] = match ($row['rule']) {
                'L2' => $row['provider'].': L2 '.$row['concrete_fqcn'].'::$'.$row['violation'].' depends on scoped '.$row['type'],
                'L3' => $row['provider'].': L3 '.$row['concrete_fqcn'].'::$'.$row['violation'].' captures a database connection',
                default => $row['provider'].': '.$row['concrete_fqcn'].' — '.$row['violation'],
            };
        }

        sort($violations);

        return $violations;
    }

    /**
     * @return array<string, array{owner_phase: string, note: string|null}>
     */
    public static function loadAllowlist(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), associative: true);

        if (! is_array($decoded)) {
            throw new RuntimeException('singleton-injection allowlist is not valid JSON: '.$path);
        }

        $map = [];

        $entries = $decoded['entries'] ?? [];
        assert(is_array($entries));
        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $package = self::scalarString($entry['package'] ?? null) ?? '';
            $class = self::scalarString($entry['class'] ?? null) ?? '';
            $parameter = self::scalarString($entry['parameter'] ?? null) ?? '';
            $owner = self::scalarString($entry['owner_phase'] ?? null) ?? '';

            if ($package === '' || $class === '' || $parameter === '' || $owner === '') {
                throw new RuntimeException('allowlist entry missing package/class/parameter/owner_phase');
            }

            $map[self::allowlistKey($package, $class, $parameter)] = [
                'owner_phase' => $owner,
                'note' => self::scalarString($entry['note'] ?? null),
            ];
        }

        return $map;
    }

    private static function scalarString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_scalar($value) && ! $value instanceof Stringable) {
            throw new RuntimeException('allowlist field must be scalar');
        }

        return (string) $value;
    }

    public static function allowlistPath(): string
    {
        return SourceScanner::repoRoot().'/scripts/singleton-injection-allowlist.json';
    }

    private static function allowlistKey(string $package, string $class, string $parameter): string
    {
        return $package.'|'.$class.'|'.$parameter;
    }

    /**
     * @return list<string>
     */
    private static function serviceProviderFiles(): array
    {
        $matches = [];

        foreach ((($entries = glob(SourceScanner::repoRoot().'/packages/*/src', GLOB_ONLYDIR)) === false ? [] : $entries) as $srcDir) {
            $matches = [
                ...$matches,
                ...SourceScanner::filesMatching($srcDir, '/\bclass\s+\w+(?:ServiceProvider|Registrar)\b/'),
            ];
        }

        $matches = array_values(array_filter(
            $matches,
            static fn (string $path): bool => str_ends_with($path, 'ServiceProvider.php') || str_ends_with($path, 'Registrar.php'),
        ));

        sort($matches);

        return $matches;
    }

    private static function packageFromProviderPath(string $path): string
    {
        if (preg_match('#/packages/([^/]+)/src/#', $path, $m) !== 1) {
            return 'unknown';
        }

        return $m[1];
    }

    private static function relativePath(string $absolute): string
    {
        $root = SourceScanner::repoRoot().'/';

        return str_starts_with($absolute, $root)
            ? substr($absolute, \strlen($root))
            : $absolute;
    }

    /**
     * @return array<string, string>
     */
    private static function parseUseMap(string $content): array
    {
        $map = [];

        if ((($qualityMatchCount = preg_match_all('/^use\s+([^;]+);/m', $content, $matches)) !== false && $qualityMatchCount > 0)) {
            foreach ($matches[1] as $useClause) {
                $useClause = trim($useClause);

                if (str_contains($useClause, '{')) {
                    continue;
                }

                if (str_contains($useClause, ' as ')) {
                    [$fqcn, $alias] = array_map(trim(...), explode(' as ', $useClause, 2));
                    $map[$alias] = ltrim($fqcn, '\\');

                    continue;
                }

                $parts = explode('\\', $useClause);
                $short = end($parts);
                $map[$short] = ltrim($useClause, '\\');
            }
        }

        return $map;
    }

    private static function parseNamespace(string $content): ?string
    {
        if (preg_match('/^\s*namespace\s+([^;]+);/m', $content, $m) !== 1) {
            return null;
        }

        return trim($m[1]);
    }

    /** @param array<string, string> $useMap */
    private static function resolveClassName(string $token, ?string $namespace, array $useMap): ?string
    {
        $token = trim($token);

        if (preg_match('/^([A-Za-z0-9_\\\\]+)::class$/', $token, $m) !== 1) {
            return null;
        }

        $name = $m[1];

        if (str_contains($name, '\\')) {
            return ltrim($name, '\\');
        }

        if (isset($useMap[$name])) {
            return $useMap[$name];
        }

        if ($namespace !== null) {
            return $namespace.'\\'.$name;
        }

        return null;
    }

    /**
     * @return list<array{abstract: string, concrete: ?string, concrete_literal: bool, constructed: list<string>}>
     */
    private static function extractSingletonRegistrations(string $content): array
    {
        return self::extractLifetimeRegistrations($content, 'singleton');
    }

    /**
     * @return list<array{abstract: string, concrete: ?string, concrete_literal: bool, constructed: list<string>}>
     */
    private static function extractScopedRegistrations(string $content): array
    {
        return self::extractLifetimeRegistrations($content, 'scoped');
    }

    /**
     * @return list<array{abstract: string, concrete: ?string, concrete_literal: bool, constructed: list<string>}>
     */
    private static function extractLifetimeRegistrations(string $content, string $method): array
    {
        $registrations = [];
        $needle = '->'.$method.'(';
        $offset = 0;

        while (($pos = strpos($content, $needle, $offset)) !== false) {
            $argsStart = $pos + \strlen($needle);
            $args = self::readParenthesizedArgumentList($content, $argsStart);

            if ($args === null) {
                $offset = $argsStart;

                continue;
            }

            $parsed = self::splitTopLevelArguments($args);
            $abstract = $parsed[0] ?? '';
            $second = $parsed[1] ?? null;
            $concrete = null;
            $concreteLiteral = false;

            if ($second !== null && preg_match('/::class$/', trim($second)) === 1) {
                $concrete = trim($second);
                $concreteLiteral = true;
            } elseif ($second === null && preg_match('/::class$/', trim($abstract)) === 1) {
                $concreteLiteral = true;
            }

            $registrations[] = [
                'abstract' => trim($abstract),
                'concrete' => $concrete,
                'concrete_literal' => $concreteLiteral && ($concrete !== null || preg_match('/::class$/', trim($abstract)) === 1),
                'constructed' => $second !== null && ! $concreteLiteral ? self::constructedClassNames($second) : [],
            ];

            $offset = $argsStart + \strlen($args) + 1;
        }

        return $registrations;
    }

    /**
     * Class names a factory closure builds with `new`, anonymous classes and self/static excluded.
     *
     * @return list<string>
     */
    private static function constructedClassNames(string $closure): array
    {
        $names = [];

        // A factory that only forwards to the container (`fn ($app) => $app->make(Holder::class)`) lives as long as Holder.
        if (preg_match('/(?:=>|\breturn)\s*\$\w+->make\(\s*(\\\\?[A-Za-z_][A-Za-z0-9_\\\\]*)::class\s*\)\s*;?\s*\}?\s*$/', trim($closure), $forward) === 1) {
            $names[] = $forward[1];
        }

        // A factory with a declared return type lives as long as that type, even when it delegates to a helper.
        if (preg_match('/^\s*(?:static\s+)?(?:fn|function)\s*\([^)]*\)\s*:\s*\??(\\\\?[A-Za-z_][A-Za-z0-9_\\\\]*)\s*(?:=>|\{)/', $closure, $declared) === 1
            && ! in_array(strtolower($declared[1]), ['void', 'mixed', 'object', 'static', 'self', 'null', 'never', 'array', 'string', 'int', 'bool', 'float', 'callable', 'iterable'], true)) {
            $names[] = $declared[1];
        }

        if (preg_match_all('/\bnew\s+(\\\\?[A-Za-z_][A-Za-z0-9_\\\\]*)\s*\(/', $closure, $matches) > 0) {
            array_push($names, ...$matches[1]);
        }

        return array_values(array_unique(array_filter(
            $names,
            static fn (string $name): bool => ! in_array(strtolower($name), ['self', 'static', 'parent', 'class'], true),
        )));
    }

    /** @param  array<string, string>  $useMap */
    private static function resolveTypeName(string $name, ?string $namespace, array $useMap): ?string
    {
        if (str_starts_with($name, '\\')) {
            return ltrim($name, '\\');
        }

        $head = strstr($name, '\\', true);
        $head = $head === false ? $name : $head;

        if (isset($useMap[$head])) {
            return $useMap[$head].substr($name, strlen($head));
        }

        return $namespace !== null ? $namespace.'\\'.$name : null;
    }

    private static function readParenthesizedArgumentList(string $content, int $argsStartIndex): ?string
    {
        // $argsStartIndex is immediately after `->singleton(` — we are already inside one open paren.
        $depth = 1;
        $length = \strlen($content);
        $inString = false;
        $stringDelimiter = '';

        for ($i = $argsStartIndex; $i < $length; $i++) {
            $char = $content[$i];

            if ($inString) {
                if ($char === '\\') {
                    $i++;

                    continue;
                }

                if ($char === $stringDelimiter) {
                    $inString = false;
                }

                continue;
            }

            if ($char === '"' || $char === "'") {
                $inString = true;
                $stringDelimiter = $char;

                continue;
            }

            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;

                if ($depth === 0) {
                    return substr($content, $argsStartIndex, $i - $argsStartIndex);
                }
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function splitTopLevelArguments(string $args): array
    {
        $parts = [];
        $current = '';
        $depth = 0;
        $length = \strlen($args);

        for ($i = 0; $i < $length; $i++) {
            $char = $args[$i];

            if ($char === '(' || $char === '[') {
                $depth++;
            } elseif ($char === ')' || $char === ']') {
                $depth--;
            }

            if ($char === ',' && $depth === 0) {
                $parts[] = trim($current);
                $current = '';

                continue;
            }

            $current .= $char;
        }

        if (trim($current) !== '') {
            $parts[] = trim($current);
        }

        return $parts;
    }

    private static function classInMonorepoSrc(string $fqcn): bool
    {
        if (! class_exists($fqcn) && ! interface_exists($fqcn)) {
            return false;
        }

        $ref = new ReflectionClass($fqcn);
        $file = $ref->getFileName();

        return $file !== false && str_contains($file, '/packages/') && str_contains($file, '/src/');
    }

    /**
     * @param  array<string, true>  $scoped
     * @return array{rule: string, parameter: string, type: string}|null
     */
    private static function constructorViolation(string $fqcn, array $scoped): ?array
    {
        if (! class_exists($fqcn) && ! interface_exists($fqcn)) {
            return null;
        }

        $ref = new ReflectionClass($fqcn);
        $constructor = $ref->getConstructor();

        if ($constructor === null) {
            return null;
        }

        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();
            $members = self::namedMembers($type);

            foreach ($members as $member) {
                if ($member->isBuiltin()) {
                    continue;
                }

                $name = $member->getName();
                $rule = match (true) {
                    self::isAnyOf($name, self::FORBIDDEN_TYPES) => 'L1',
                    self::isAnyOf($name, self::CONNECTION_TYPES) => 'L3',
                    isset($scoped[$name]) => 'L2',
                    default => null,
                };

                if ($rule !== null) {
                    return ['rule' => $rule, 'parameter' => $parameter->getName(), 'type' => $name];
                }
            }
        }

        return null;
    }

    /**
     * Named types of a constructor parameter, flattening unions, intersections and DNF combinations.
     *
     * @return list<ReflectionNamedType>
     */
    private static function namedMembers(?ReflectionType $type): array
    {
        if ($type instanceof ReflectionNamedType) {
            return [$type];
        }

        if (! $type instanceof ReflectionUnionType && ! $type instanceof ReflectionIntersectionType) {
            return [];
        }

        $members = [];

        foreach ($type->getTypes() as $member) {
            array_push($members, ...self::namedMembers($member));
        }

        return $members;
    }

    /**
     * @param  list<string>  $types
     */
    private static function isAnyOf(string $name, array $types): bool
    {
        foreach ($types as $type) {
            if (is_a($name, $type, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Abstract types registered with `->scoped(X::class ...)` in any provider or registrar.
     *
     * @return array<string, true>
     */
    private static function scopedAbstracts(): array
    {
        $scoped = [];

        foreach (self::serviceProviderFiles() as $path) {
            $content = (string) file_get_contents($path);
            $useMap = self::parseUseMap($content);
            $namespace = self::parseNamespace($content);

            foreach (self::extractScopedRegistrations($content) as $registration) {
                $abstract = self::resolveClassName($registration['abstract'], $namespace, $useMap);

                if ($abstract !== null) {
                    $scoped[$abstract] = true;
                }
            }
        }

        return $scoped;
    }
}
