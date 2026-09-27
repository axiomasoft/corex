<?php

declare(strict_types=1);

namespace CoreX\Tests\Support;

use ReflectionClass;
use ReflectionNamedType;
use ReflectionType;
use RuntimeException;

/**
 * P4.12 — scan package ServiceProviders for singleton ::class literals and flag
 * constructors that capture Application, Request, or Config Repository.
 */
final class SingletonInjectionGate
{
    /** @var list<string> */
    public const FORBIDDEN_TYPES = [
        'Illuminate\Contracts\Foundation\Application',
        'Illuminate\Http\Request',
        'Illuminate\Contracts\Config\Repository',
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
     * }>
     */
    public static function auditRows(): array
    {
        $rows = [];

        foreach (self::serviceProviderFiles() as $providerPath) {
            $package = self::packageFromProviderPath($providerPath);
            $content = (string) file_get_contents($providerPath);
            $useMap = self::parseUseMap($content);
            $namespace = self::parseNamespace($content);

            foreach (self::extractSingletonRegistrations($content) as $registration) {
                $abstractFqcn = self::resolveClassName($registration['abstract'], $namespace, $useMap);
                $concreteFqcn = $registration['concrete'] !== null
                    ? self::resolveClassName($registration['concrete'], $namespace, $useMap)
                    : $abstractFqcn;

                $staticParse = $registration['concrete_literal'];
                $captures = false;
                $violation = null;
                $verdict = 'ok';

                if (! $staticParse) {
                    $verdict = 'static-limit';
                } elseif ($concreteFqcn !== null && self::classInMonorepoSrc($concreteFqcn)) {
                    $violation = self::constructorForbiddenParameter($concreteFqcn);
                    $captures = $violation !== null;

                    if ($captures) {
                        $verdict = 'allowlist-required';
                    } elseif ($staticParse) {
                        $verdict = 'stateless-ok';
                    }
                } elseif ($abstractFqcn !== null && $staticParse && $concreteFqcn === $abstractFqcn
                    && self::classInMonorepoSrc($abstractFqcn)) {
                    $violation = self::constructorForbiddenParameter($abstractFqcn);
                    $captures = $violation !== null;
                    $verdict = $captures ? 'allowlist-required' : 'stateless-ok';
                }

                $rows[] = [
                    'package' => $package,
                    'provider' => self::relativePath($providerPath),
                    'abstract' => $abstractFqcn ?? $registration['abstract'],
                    'lifetime' => 'singleton',
                    'concrete_fqcn' => $concreteFqcn,
                    'static_parse' => $staticParse,
                    'captures_context' => $captures,
                    'verdict' => $verdict,
                    'violation' => $violation,
                ];
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

            $violations[] = $row['provider'].': '.$row['concrete_fqcn'].' — '.$row['violation'];
        }

        sort($violations);

        return $violations;
    }

    /**
     * @return array<string, array{owner_phase: string, note?: string}>
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

        foreach ($decoded['entries'] ?? [] as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $package = (string) ($entry['package'] ?? '');
            $class = (string) ($entry['class'] ?? '');
            $parameter = (string) ($entry['parameter'] ?? '');
            $owner = (string) ($entry['owner_phase'] ?? '');

            if ($package === '' || $class === '' || $parameter === '' || $owner === '') {
                throw new RuntimeException('allowlist entry missing package/class/parameter/owner_phase');
            }

            $map[self::allowlistKey($package, $class, $parameter)] = [
                'owner_phase' => $owner,
                'note' => isset($entry['note']) ? (string) $entry['note'] : null,
            ];
        }

        return $map;
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

        foreach (glob(SourceScanner::repoRoot().'/packages/*/src', GLOB_ONLYDIR) ?: [] as $srcDir) {
            $matches = [
                ...$matches,
                ...SourceScanner::filesMatching($srcDir, '/\bclass\s+\w+ServiceProvider\b/'),
            ];
        }

        $matches = array_values(array_filter(
            $matches,
            static fn (string $path): bool => str_ends_with($path, 'ServiceProvider.php'),
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

        if (preg_match_all('/^use\s+([^;]+);/m', $content, $matches)) {
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
     * @return list<array{abstract: string, concrete: ?string, concrete_literal: bool}>
     */
    private static function extractSingletonRegistrations(string $content): array
    {
        return self::extractLifetimeRegistrations($content, 'singleton');
    }

    /**
     * @return list<array{abstract: string, concrete: ?string, concrete_literal: bool}>
     */
    private static function extractScopedRegistrations(string $content): array
    {
        return self::extractLifetimeRegistrations($content, 'scoped');
    }

    /**
     * @return list<array{abstract: string, concrete: ?string, concrete_literal: bool}>
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
            ];

            $offset = $argsStart + \strlen($args) + 1;
        }

        return $registrations;
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

    private static function constructorForbiddenParameter(string $fqcn): ?string
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

            if (! self::typeIsForbidden($type)) {
                continue;
            }

            return $parameter->getName();
        }

        return null;
    }

    private static function typeIsForbidden(?ReflectionType $type): bool
    {
        if ($type instanceof ReflectionNamedType) {
            if ($type->isBuiltin()) {
                return false;
            }

            $name = $type->getName();

            foreach (self::FORBIDDEN_TYPES as $forbidden) {
                if (is_a($name, $forbidden, true)) {
                    return true;
                }
            }
        }

        return false;
    }
}
