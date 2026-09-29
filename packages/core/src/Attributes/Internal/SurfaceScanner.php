<?php

declare(strict_types=1);

namespace CoreX\Attributes\Internal;

use Composer\Autoload\ClassLoader;
use FilesystemIterator;
use PhpParser\Error;
use PhpParser\Modifiers;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use PHPStan\PhpDocParser\Ast as Doc;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\ParserConfig;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;
use SplFileInfo;

/** @internal Parses production source without executing or booting the inspected packages. */
final class SurfaceScanner
{
    private const string API = 'CoreX\\Attributes\\Api';

    private const string SPI = 'CoreX\\Attributes\\Spi';

    private const string KIND = 'CoreX\\Attributes\\SpiKind';

    private const array BUILTINS = ['int', 'integer', 'float', 'double', 'string', 'bool', 'boolean', 'true', 'false', 'null', 'void', 'never', 'mixed', 'object', 'array', 'list', 'iterable', 'callable', 'resource', 'scalar', 'numeric', 'numeric-string', 'non-empty-string', 'literal-string', 'lowercase-string', 'uppercase-string', 'class-string', 'interface-string', 'trait-string', 'callable-string', 'array-key', 'positive-int', 'negative-int', 'non-negative-int', 'non-positive-int', 'non-zero-int', 'non-empty-array', 'non-empty-list', 'key-of', 'value-of', 'int-mask', 'int-mask-of', 'min', 'max'];

    private Parser $parser;

    private Standard $printer;

    private Lexer $lexer;

    private PhpDocParser $docParser;

    /** @var array<string, SourceType> */
    private array $types = [];

    /** @var array<string, string> */
    private array $names = [];

    /** @var array<string, array{layer: string, since: ?string}> */
    private array $metadata = [];

    /** @var list<SurfaceDiagnostic> */
    private array $diagnostics = [];

    /** @var array<string, string> */
    private array $packagePaths = [];

    /** @var array<string, array<string, mixed>> */
    private array $records = [];

    /** @var array<string, list<array{target: string, path: string, line: int}>> */
    private array $payloads = [];

    /** @var list<array{file: string, owner: string, name: string, line: int}> */
    private array $references = [];

    /** @var array<string, bool> */
    private array $resolving = [];

    /** @var array<string, array<string, Doc\Type\TypeNode>> */
    private array $aliases = [];

    /** @var array<string, bool> */
    private array $loadedVendor = [];

    /** @var array<string, bool> */
    private array $parsedFiles = [];

    public function __construct()
    {
        $this->parser = (new ParserFactory)->createForNewestSupportedVersion();
        $this->printer = new Standard;
        $config = new ParserConfig(['lines' => true]);
        $this->lexer = new Lexer($config);
        $const = new ConstExprParser($config);
        $this->docParser = new PhpDocParser($config, new TypeParser($config, $const), $const);
    }

    public function scan(string $root): SurfaceReport
    {
        $root = realpath($root);

        if ($root === false) {
            throw new RuntimeException('Source root does not exist');
        }
        $this->reset();
        $manifests = array_merge($this->glob($root.'/packages/*/composer.json'), $this->glob($root.'/products/*/composer.json'));
        sort($manifests);

        if ($manifests === []) {
            throw new RuntimeException('No package manifests found');
        }
        foreach ($manifests as $manifest) {
            $data = json_decode($this->read($manifest), true, flags: JSON_THROW_ON_ERROR);

            if (! is_array($data) || ! is_string($data['name'] ?? null) || ! is_array($data['autoload'] ?? null)) {
                throw new RuntimeException("Invalid package manifest: {$manifest}");
            }
            $owner = $data['name'];

            if (isset($this->packagePaths[$owner])) {
                throw new RuntimeException("Duplicate package owner: {$owner}");
            }
            $directory = dirname($manifest);
            $this->packagePaths[$owner] = substr($directory, strlen($root) + 1);
            $product = str_starts_with($this->packagePaths[$owner], 'products/');
            $paths = [];
            foreach ($data['autoload'] as $kind => $mapping) {
                if (! in_array($kind, ['psr-4', 'psr-0', 'classmap', 'files'], true)) {
                    throw new RuntimeException("Unsupported production autoload: {$kind}");
                }

                if (! is_array($mapping)) {
                    throw new RuntimeException('Invalid production autoload map');
                }
                foreach ($mapping as $entries) {
                    foreach (is_array($entries) ? $entries : [$entries] as $entry) {
                        if (! is_string($entry)) {
                            throw new RuntimeException('Invalid production autoload path');
                        }
                        foreach ($this->files($directory.'/'.$entry) as $file) {
                            $paths[$file] = true;
                        }
                    }
                }
            }
            // Configuration is parsed as source too: class-string edges never require execution.
            foreach ($this->files($directory.'/config', optional: true) as $file) {
                $paths[$file] = true;
            }
            ksort($paths);
            foreach (array_keys($paths) as $file) {
                $realFile = realpath($file);
                $realDirectory = realpath($directory);

                if ($realFile === false || $realDirectory === false || ! str_starts_with($realFile, $realDirectory.'/')) {
                    throw new RuntimeException('Production source escapes its Composer owner');
                }
                $this->parseFile($file, substr($file, strlen($root) + 1), $owner, $product);
            }
        }
        ksort($this->types);
        $owned = $this->types;
        foreach ($owned as $name => $type) {
            $this->metadata[$name] = $this->classify($type);
        }
        foreach ($owned as $name => $type) {
            $this->record($name);
            $this->validateDeclaration($type);
        }
        foreach ($owned as $type) {
            $layer = $this->metadata[$type->name]['layer'];

            if ($layer === 'api' || ($layer === 'spi-call' && str_contains($type->name, '\\Events\\'))) {
                $this->walkPayload($type, $type->name, [], $type->name);
            }
        }
        $this->validateReferences($root);
        $owners = [];
        $output = [];
        foreach ($this->packagePaths as $package => $path) {
            if (! str_starts_with($path, 'products/')) {
                $output[$package] = ['schema' => 'php-api/1', 'package' => $package, 'symbols' => []];
            }
        }
        foreach ($owned as $name => $type) {
            $owners[$name] = $type->owner;

            if (! $type->product && in_array($this->metadata[$name]['layer'], ['api', 'spi-call', 'spi-implement'], true)) {
                $record = $this->records[$name];

                if ($this->metadata[$name]['layer'] !== 'spi-implement') {
                    foreach (['methods', 'properties', 'constants'] as $category) {
                        $members = $record[$category] ?? [];
                        $record[$category] = is_array($members) ? array_filter($members, static fn (mixed $member): bool => is_array($member) && ($member['visibility'] ?? '') === 'public') : [];
                    }
                }
                $output[$type->owner]['symbols'][$name] = $record;
            }
        }
        ksort($output);
        usort($this->diagnostics, static fn (SurfaceDiagnostic $a, SurfaceDiagnostic $b): int => [$a->file, $a->line, $a->code, $a->path, $a->message] <=> [$b->file, $b->line, $b->code, $b->path, $b->message]);
        $unique = [];
        foreach ($this->diagnostics as $diagnostic) {
            $unique[json_encode($diagnostic->toArray(), JSON_THROW_ON_ERROR)] = $diagnostic;
        }

        return new SurfaceReport($output, $owners, array_values($unique), $this->packagePaths);
    }

    private function reset(): void
    {
        $this->types = $this->names = $this->metadata = $this->diagnostics = $this->packagePaths = $this->records = $this->payloads = $this->references = $this->resolving = $this->aliases = $this->loadedVendor = $this->parsedFiles = [];
    }

    /** @return list<string> */
    private function glob(string $pattern): array
    {
        $paths = glob($pattern);

        return $paths === false ? [] : $paths;
    }

    /** @return list<string> */
    private function files(string $path, bool $optional = false): array
    {
        if (is_file($path)) {
            return str_ends_with($path, '.php') ? [$path] : [];
        }

        if (! is_dir($path)) {
            if ($optional) {
                return [];
            }

            throw new RuntimeException("Missing production source: {$path}");
        }
        $files = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        return $files;
    }

    private function read(string $file): string
    {
        $source = file_get_contents($file);

        return $source === false ? throw new RuntimeException("Cannot read source: {$file}") : $source;
    }

    private function parseFile(string $absolute, string $relative, string $owner, bool $product): void
    {
        $key = $owner.':'.$absolute;

        if (isset($this->parsedFiles[$key])) {
            return;
        }
        $this->parsedFiles[$key] = true;

        try {
            $nodes = $this->parser->parse($this->read($absolute)) ?? [];
            $nodes = (new NodeTraverser(new NameResolver))->traverse($nodes);
        } catch (Error $e) {
            throw new RuntimeException($relative.': '.$e->getMessage(), previous: $e);
        }
        $this->collect($nodes, $relative, $owner, $product, '');

        if ($owner !== '@vendor') {
            foreach ((new NodeFinder)->findInstanceOf($nodes, Node\Name::class) as $node) {
                if ($node instanceof Node\Name\FullyQualified) {
                    $this->references[] = ['file' => $relative, 'owner' => $owner, 'name' => $node->toString(), 'line' => $node->getStartLine()];
                }
            }
            foreach ((new NodeFinder)->findInstanceOf($nodes, Node\Scalar\String_::class) as $node) {
                if (str_contains($node->value, '\\')) {
                    $this->references[] = ['file' => $relative, 'owner' => $owner, 'name' => ltrim($node->value, '\\'), 'line' => $node->getStartLine()];
                }
            }
        }
    }

    /**
     * @param  array<Node>  $nodes
     * @param  array<string, string>  $imports
     */
    private function collect(array $nodes, string $file, string $owner, bool $product, string $namespace, array $imports = []): void
    {
        foreach ($nodes as $node) {
            if ($node instanceof Node\Stmt\Use_ || $node instanceof Node\Stmt\GroupUse) {
                foreach ($node->uses as $use) {
                    if (($use->type !== 0 ? $use->type : $node->type) === Node\Stmt\Use_::TYPE_NORMAL) {
                        $name = ($node instanceof Node\Stmt\GroupUse ? $node->prefix.'\\' : '').$use->name;
                        $imports[strtolower($use->getAlias()->toString())] = $name;

                        if ($owner !== '@vendor') {
                            $this->references[] = ['file' => $file, 'owner' => $owner, 'name' => $name, 'line' => $use->getStartLine()];
                        }
                    }
                }
            }
        }
        foreach ($nodes as $node) {
            if ($node instanceof Node\Stmt\Namespace_) {
                $this->collect($node->stmts, $file, $owner, $product, $node->name?->toString() ?? '');
            } elseif ($node instanceof Node\Stmt\ClassLike && $node->name !== null) {
                $name = $node->namespacedName?->toString() ?? $node->name->toString();

                if (isset($this->names[strtolower($name)])) {
                    throw new RuntimeException("Duplicate FQCN owner: {$name} ({$file})");
                }
                $this->names[strtolower($name)] = $name;
                $this->types[$name] = new SourceType($name, $owner, $file, $product, $node, $namespace, $imports);
                $this->collect($node->stmts, $file, $owner, $product, $namespace, $imports);
            } elseif ($node instanceof Node\Stmt\Function_ && ! $product && ! str_contains('\\'.$namespace.'\\', '\\Internal\\')) {
                $this->diagnostics[] = new SurfaceDiagnostic('API-FUNCTION', $file, $node->getStartLine(), $namespace.'\\'.$node->name, 'declaration', 'Public functions cannot carry class metadata');
                $this->collect($node->stmts, $file, $owner, $product, $namespace, $imports);
            } elseif (property_exists($node, 'stmts') && is_array($node->stmts)) {
                // Conditional named declarations still belong to the production surface.
                $this->collect(array_filter($node->stmts, static fn (mixed $child): bool => $child instanceof Node), $file, $owner, $product, $namespace, $imports);
            }
        }
    }

    /** @return array{layer: string, since: ?string} */
    private function classify(SourceType $type): array
    {
        $found = [];
        foreach ($type->node->attrGroups as $group) {
            foreach ($group->attrs as $attribute) {
                if (in_array(strtolower($attribute->name->toString()), [strtolower(self::API), strtolower(self::SPI)], true)) {
                    $found[] = $attribute;
                }
            }
        }
        $internal = str_contains('\\'.$type->name.'\\', '\\Internal\\');

        if ($found === []) {
            if (! $internal && ! $type->product && $type->owner !== '@vendor') {
                $this->error($type, 'API-UNCLASSIFIED', 'declaration', 'Missing Api/Spi annotation');
            }

            return ['layer' => $internal ? 'internal' : ($type->product ? 'consumer' : 'unclassified'), 'since' => null];
        }

        if (count($found) !== 1) {
            $this->error($type, 'API-METADATA', 'declaration', 'Exactly one Api or Spi annotation required');
        }

        if ($internal || str_contains($type->node->getDocComment()?->getText() ?? '', '@internal')) {
            $this->error($type, 'API-INTERNAL-ANNOTATION', 'declaration', 'Internal declaration cannot be Api/Spi');
        }
        $attribute = $found[0];
        $spi = strtolower($attribute->name->toString()) === strtolower(self::SPI);
        $args = [];
        $positions = $spi ? ['kind', 'since'] : ['since'];
        foreach ($attribute->args as $i => $arg) {
            $key = $arg->name?->toString() ?? ($positions[$i] ?? 'invalid');

            if (! in_array($key, $positions, true) || isset($args[$key]) || $arg->unpack) {
                $this->error($type, 'API-METADATA', 'attribute', 'Unknown, duplicate or unpacked argument');
            }
            $args[$key] = $arg->value;
        }
        $since = isset($args['since']) && $args['since'] instanceof Node\Scalar\String_ ? $args['since']->value : (isset($args['since']) ? '' : '1.0');

        if ($since !== '1.0' && preg_match('/^(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)(?:-(?:0|[1-9]\d*|[0-9A-Za-z-]*[A-Za-z-][0-9A-Za-z-]*)(?:\.(?:0|[1-9]\d*|[0-9A-Za-z-]*[A-Za-z-][0-9A-Za-z-]*))*)?(?:\+[0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*)?$/D', $since) !== 1) {
            $this->error($type, 'API-METADATA', 'since', 'Expected numeric version');
        }
        $layer = 'api';

        if ($spi) {
            $kind = $args['kind'] ?? null;

            if (! $kind instanceof Node\Expr\ClassConstFetch || ! $kind->class instanceof Node\Name || strtolower($kind->class->toString()) !== strtolower(self::KIND) || ! $kind->name instanceof Node\Identifier || ! in_array($kind->name->toString(), ['Implement', 'Call'], true)) {
                $this->error($type, 'API-METADATA', 'kind', 'Expected SpiKind::Implement or SpiKind::Call');
                $layer = 'invalid';
            } else {
                $layer = 'spi-'.strtolower($kind->name->toString());
            }
        }

        return ['layer' => $layer, 'since' => $since];
    }

    private function error(SourceType $type, string $code, string $path, string $message, ?int $line = null): void
    {
        $this->diagnostics[] = new SurfaceDiagnostic($code, $type->file, $line ?? $type->node->getStartLine(), $type->name, $path, $message);
    }

    private function lookup(string $name): ?SourceType
    {
        $name = $this->names[strtolower($name)] ?? $name;

        if (isset($this->types[$name])) {
            return $this->types[$name];
        }

        if (isset($this->loadedVendor[$name])) {
            return null;
        }
        $this->loadedVendor[$name] = true;
        foreach (ClassLoader::getRegisteredLoaders() as $loader) {
            $file = $loader->findFile($name);

            // The inspected tree is authoritative; the host autoloader may only supply vendor dependencies.
            if ($file !== false && str_contains($file, '/vendor/') && ! str_contains($file, '/vendor/axiomasoft/')) {
                $this->parseFile($file, '@vendor/'.$name, '@vendor', false);

                break;
            }
        }

        return $this->types[$name] ?? null;
    }

    /** @return list<string> */
    private function parents(SourceType $type): array
    {
        $node = $type->node;
        $parents = [];

        if ($node instanceof Node\Stmt\Class_ && $node->extends !== null) {
            $parents[] = $node->extends->toString();
        }

        if ($node instanceof Node\Stmt\Interface_) {
            $parents = array_map(static fn (Node\Name $name): string => $name->toString(), $node->extends);
        }
        foreach ($node->getTraitUses() as $use) {
            foreach ($use->traits as $trait) {
                $parents[] = $trait->toString();
            }
        }

        return array_values($parents);
    }

    /** @return array<string, mixed> */
    private function record(string $name): array
    {
        $name = $this->names[strtolower($name)] ?? $name;

        if (isset($this->records[$name])) {
            return $this->records[$name];
        }
        $type = $this->lookup($name);

        if ($type === null) {
            return $this->builtinRecord($name);
        }

        if (isset($this->resolving[$name])) {
            $this->error($type, 'API-INHERITANCE', 'extends', 'Cyclic inheritance');

            return [];
        }
        $this->resolving[$name] = true;
        $node = $type->node;
        $meta = $this->metadata[$name] ?? ['layer' => 'external', 'since' => null];
        $members = ['methods' => [], 'properties' => [], 'constants' => []];
        foreach ($this->parents($type) as $parent) {
            $inherited = $this->record($parent);

            if ($inherited === []) {
                $this->error($type, 'API-UNRESOLVED-TYPE', 'extends', $parent);
            }
            foreach (array_keys($members) as $category) {
                if (isset($inherited[$category]) && is_array($inherited[$category])) {
                    $members[$category] = array_replace($members[$category], $inherited[$category]);
                }
            }
            foreach ($this->payloads[$parent] ?? [] as $reference) {
                $this->payloads[$name][] = ['target' => $reference['target'], 'path' => 'inherited('.$parent.').'.$reference['path'], 'line' => $node->getStartLine()];
            }
        }
        $doc = $this->doc($type, $node, 'class');
        foreach ($node->getTraitUses() as $use) {
            if ($use->adaptations !== []) {
                $this->error($type, 'API-INHERITANCE', 'traits', 'Trait adaptations require explicit resolution');
            }
        }
        foreach ($node->stmts as $member) {
            if ($member instanceof Node\Stmt\ClassMethod && ! $member->isPrivate()) {
                $path = 'methods.'.$member->name;
                $methodDoc = $this->doc($type, $member, $path);
                $parameters = [];
                foreach ($member->params as $param) {
                    foreach ($param->attrGroups as $group) {
                        foreach ($group->attrs as $attribute) {
                            if (in_array(strtolower($attribute->name->toString()), [strtolower(self::API), strtolower(self::SPI)], true)) {
                                $this->error($type, 'API-METADATA', $path.'.parameter', 'Api/Spi must target the type', $param->getStartLine());
                            }
                        }
                    }
                    $paramName = $param->var instanceof Node\Expr\Variable && is_string($param->var->name) ? $param->var->name : '?';

                    if ($meta['layer'] === 'api') {
                        $parameterDocs = [];
                        foreach (['@param', '@phpstan-param', '@psalm-param'] as $tag) {
                            foreach ($methodDoc[$tag] ?? [] as $value) {
                                if (preg_match('/\\$'.preg_quote($paramName, '/').'(?=\\s|$)/', $value) === 1) {
                                    $parameterDocs[] = $value;
                                }
                            }
                        }
                        $this->ambiguousNative($type, $param->type, $parameterDocs, $path.'.parameters.'.$paramName);
                    }
                    $parameters[] = ['name' => $paramName, 'type' => $this->native($type, $param->type, $path.'.parameters.'.$paramName), 'by_reference' => $param->byRef, 'variadic' => $param->variadic, 'optional' => $param->default !== null || $param->variadic, 'default' => $this->expression($param->default), 'flags' => $param->flags];

                    if ($param->flags !== 0 && ($param->flags & Modifiers::PRIVATE) === 0) {
                        $members['properties'][$paramName] = ['flags' => $param->flags, 'type' => $this->native($type, $param->type, 'properties.'.$paramName), 'readonly' => ($param->flags & Modifiers::READONLY) !== 0 || ($node instanceof Node\Stmt\Class_ && $node->isReadonly()), 'visibility' => ($param->flags & Modifiers::PROTECTED) !== 0 ? 'protected' : 'public', 'static' => false, 'default' => null, 'doc' => $methodDoc, 'hooks' => []];
                    }
                }

                if ($meta['layer'] === 'api' && ! in_array($member->name->toString(), ['__construct', '__destruct'], true)) {
                    $this->ambiguousNative($type, $member->returnType, array_merge($methodDoc['@return'] ?? [], $methodDoc['@phpstan-return'] ?? [], $methodDoc['@psalm-return'] ?? []), $path.'.return');
                }
                $members['methods'][$member->name->toString()] = ['visibility' => $member->isProtected() ? 'protected' : 'public', 'static' => $member->isStatic(), 'final' => $member->isFinal(), 'abstract' => $member->isAbstract() || $node instanceof Node\Stmt\Interface_, 'by_reference' => $member->byRef, 'parameters' => $parameters, 'return' => $this->native($type, $member->returnType, $path.'.return'), 'doc' => $methodDoc];
            } elseif ($member instanceof Node\Stmt\Property && ! $member->isPrivate()) {
                foreach ($member->props as $property) {
                    $path = 'properties.'.$property->name;
                    $hooks = [];
                    foreach ($member->hooks as $hook) {
                        $hooks[$hook->name->toString()] = ['flags' => $hook->flags, 'by_reference' => $hook->byRef, 'parameters' => array_map(fn (Node\Param $p): array => ['type' => $this->native($type, $p->type, $path.'.hook'), 'name' => $p->var instanceof Node\Expr\Variable && is_string($p->var->name) ? $p->var->name : '?'], $hook->params)];
                    }
                    $members['properties'][$property->name->toString()] = ['flags' => $member->flags, 'type' => $this->native($type, $member->type, $path), 'readonly' => $member->isReadonly() || ($node instanceof Node\Stmt\Class_ && $node->isReadonly()), 'visibility' => $member->isProtected() ? 'protected' : 'public', 'static' => $member->isStatic(), 'default' => $this->expression($property->default), 'doc' => $this->doc($type, $member, $path), 'hooks' => $hooks];
                }
            } elseif ($member instanceof Node\Stmt\ClassConst && ! $member->isPrivate()) {
                foreach ($member->consts as $constant) {
                    $members['constants'][$constant->name->toString()] = ['type' => $this->native($type, $member->type, 'constants.'.$constant->name), 'value' => $this->expression($constant->value), 'visibility' => $member->isProtected() ? 'protected' : 'public', 'final' => $member->isFinal(), 'doc' => $this->doc($type, $member, 'constants.'.$constant->name)];
                }
            }

            if ($member instanceof Node\Stmt\ClassMethod || $member instanceof Node\Stmt\Property || $member instanceof Node\Stmt\ClassConst || $member instanceof Node\Stmt\EnumCase) {
                foreach ($member->attrGroups as $group) {
                    foreach ($group->attrs as $attribute) {
                        if (in_array(strtolower($attribute->name->toString()), [strtolower(self::API), strtolower(self::SPI)], true)) {
                            $this->error($type, 'API-METADATA', 'member', 'Api/Spi must target the type', $member->getStartLine());
                        }
                    }
                }
            }
        }
        foreach ($members as &$list) {
            ksort($list);
        }
        unset($list);
        $cases = [];

        if ($node instanceof Node\Stmt\Enum_) {
            foreach ($node->stmts as $case) {
                if ($case instanceof Node\Stmt\EnumCase) {
                    $cases[$case->name->toString()] = $this->expression($case->expr);
                }
            }
        }
        ksort($cases);
        $implements = ($node instanceof Node\Stmt\Class_ || $node instanceof Node\Stmt\Enum_) ? array_map(static fn (Node\Name $n): string => $n->toString(), $node->implements) : [];
        sort($implements);
        $result = ['kind' => match (true) {
            $node instanceof Node\Stmt\Interface_ => 'interface', $node instanceof Node\Stmt\Trait_ => 'trait', $node instanceof Node\Stmt\Enum_ => 'enum', default => 'class',
        }, ...$meta, 'final' => $node instanceof Node\Stmt\Class_ && $node->isFinal(), 'readonly' => $node instanceof Node\Stmt\Class_ && $node->isReadonly(), 'abstract' => $node instanceof Node\Stmt\Class_ && $node->isAbstract(), 'extends' => $this->parents($type), 'implements' => $implements, 'doc' => $doc, ...$members, 'enum_backing' => $node instanceof Node\Stmt\Enum_ ? $node->scalarType?->toString() : null, 'enum_cases' => $cases];
        unset($this->resolving[$name]);

        return $this->records[$name] = $result;
    }

    /** @return array<string, mixed> */
    private function builtinRecord(string $name): array
    {
        if (! class_exists($name, false) && ! interface_exists($name, false)) {
            return [];
        }
        $class = new ReflectionClass($name);

        if (! $class->isInternal()) {
            return [];
        }
        $methods = $properties = $constants = [];
        foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_PROTECTED) as $method) {
            $parameters = [];
            foreach ($method->getParameters() as $parameter) {
                $parameters[] = ['name' => $parameter->getName(), 'type' => $parameter->hasType() ? (string) $parameter->getType() : null,
                    'by_reference' => $parameter->isPassedByReference(), 'variadic' => $parameter->isVariadic(),
                    'optional' => $parameter->isOptional(), 'default' => $parameter->isDefaultValueAvailable() ? var_export($parameter->getDefaultValue(), true) : null, 'flags' => 0];
            }
            $methods[$method->getName()] = ['visibility' => $method->isProtected() ? 'protected' : 'public', 'static' => $method->isStatic(),
                'final' => $method->isFinal(), 'abstract' => $method->isAbstract(), 'by_reference' => $method->returnsReference(),
                'parameters' => $parameters, 'return' => $method->hasReturnType() ? (string) $method->getReturnType() : null, 'doc' => []];
        }
        foreach ($class->getProperties(ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED) as $property) {
            $properties[$property->getName()] = ['flags' => $property->getModifiers(), 'type' => $property->hasType() ? (string) $property->getType() : null,
                'readonly' => $property->isReadOnly(), 'visibility' => $property->isProtected() ? 'protected' : 'public',
                'static' => $property->isStatic(), 'default' => var_export($property->getDefaultValue(), true), 'doc' => [], 'hooks' => []];
        }
        foreach ($class->getReflectionConstants() as $constant) {
            if (! $constant->isPrivate()) {
                $constants[$constant->getName()] = ['type' => $constant->hasType() ? (string) $constant->getType() : null,
                    'value' => var_export($constant->getValue(), true), 'visibility' => $constant->isProtected() ? 'protected' : 'public', 'final' => $constant->isFinal(), 'doc' => []];
            }
        }

        return ['methods' => $methods, 'properties' => $properties, 'constants' => $constants];
    }

    /** @param list<string> $docs */
    private function ambiguousNative(SourceType $type, Node\Identifier|Node\Name|Node\ComplexType|null $node, array $docs, string $path): void
    {
        if ($node instanceof Node\NullableType) {
            $this->ambiguousNative($type, $node->type, $docs, $path);

            return;
        }

        if ($node instanceof Node\UnionType || $node instanceof Node\IntersectionType) {
            foreach ($node->types as $part) {
                $this->ambiguousNative($type, $part, $docs, $path);
            }

            return;
        }

        if ($node !== null && (! $node instanceof Node\Identifier || ! in_array($node->toString(), ['mixed', 'object', 'array', 'iterable'], true))) {
            return;
        }

        if ($docs === [] || preg_match('/^(?:mixed|object|array|iterable|list|non-empty-list|non-empty-array)(?:\\s|$)/', implode(' ', $docs)) === 1) {
            $this->error($type, 'API-UNTYPED-PAYLOAD', $path, 'Ambiguous native payload requires a concrete PHPDoc type or bounded template');
        }
    }

    private function expression(?Node\Expr $node): ?string
    {
        return $node === null ? null : $this->printer->prettyPrintExpr($node);
    }

    private function native(SourceType $type, Node\Identifier|Node\Name|Node\ComplexType|null $node, string $path): ?string
    {
        if ($node === null) {
            return null;
        }

        if ($node instanceof Node\NullableType) {
            return '?'.$this->native($type, $node->type, $path.'.nullable');
        }

        if ($node instanceof Node\UnionType || $node instanceof Node\IntersectionType) {
            return '('.implode($node instanceof Node\UnionType ? '|' : '&', array_map(fn ($n): ?string => $this->native($type, $n, $path.'.type'), $node->types)).')';
        }

        if (! $node instanceof Node\Name && ! $node instanceof Node\Identifier) {
            $this->error($type, 'API-UNRESOLVED-TYPE', $path, 'Unsupported native type');

            return null;
        }
        $name = $node->toString();

        if ($node instanceof Node\Name) {
            $name = $node instanceof Node\Name\FullyQualified ? $name : $type->resolve($name);
            $this->payloads[$type->name][] = ['target' => $name, 'path' => $path, 'line' => $node->getStartLine()];
        }

        return $node instanceof Node\Name && in_array($node->toString(), ['self', 'static', 'parent'], true) ? $node->toString().'('.$name.')' : $name;
    }

    /** @return array<string, list<string>> */
    private function doc(SourceType $type, Node $node, string $path): array
    {
        $text = $node->getDocComment()?->getText();

        if ($text === null) {
            return [];
        }
        $parsed = $this->docParser->parse(new TokenIterator($this->lexer->tokenize($text)));
        $aliases = $this->aliases[$type->name] ?? [];
        foreach ($parsed->getTags() as $tag) {
            if ($tag->value instanceof Doc\PhpDoc\TypeAliasTagValueNode) {
                $aliases[$tag->value->alias] = $tag->value->type;
            } elseif ($tag->value instanceof Doc\PhpDoc\TemplateTagValueNode) {
                $aliases[$tag->value->name] = $tag->value->bound ?? new Doc\Type\IdentifierTypeNode('mixed');
            } elseif ($tag->value instanceof Doc\PhpDoc\TypeAliasImportTagValueNode) {
                $source = $this->lookup($type->resolve($tag->value->importedFrom->name));

                if ($source !== null && $source->name !== $type->name && ! isset($this->resolving['alias:'.$source->name])) {
                    $this->resolving['alias:'.$source->name] = true;
                    $this->doc($source, $source->node, 'class');
                    unset($this->resolving['alias:'.$source->name]);
                }
                $alias = $source !== null ? ($this->aliases[$source->name][$tag->value->importedAlias] ?? null) : null;

                if ($alias === null) {
                    $this->error($type, 'API-UNRESOLVED-TYPE', $path.'.alias', 'Unresolved imported type alias');
                } else {
                    $alias = (new Doc\NodeTraverser([new Doc\NodeVisitor\CloningVisitor]))->traverse([$alias])[0];

                    if ($alias instanceof Doc\Type\TypeNode) {
                        $this->walkDoc($source, $alias, 'class.alias', $this->aliases[$source->name] ?? [], [], $source->node->getStartLine());
                        $aliases[$tag->value->importedAs ?? $tag->value->importedAlias] = $alias;
                    }
                }
            }
        }

        if ($node instanceof Node\Stmt\ClassLike) {
            $this->aliases[$type->name] = $aliases;
        }
        $out = [];
        foreach ($parsed->getTags() as $tag) {
            if ($tag->value instanceof Doc\PhpDoc\InvalidTagValueNode) {
                $this->error($type, 'API-UNRESOLVED-TYPE', $path.'.'.$tag->name, 'Malformed PHPDoc', $node->getStartLine());

                continue;
            }

            if ($tag->value instanceof Doc\PhpDoc\GenericTagValueNode) {
                if ($tag->name === '@corex-model-extension') {
                    $out[$tag->name][] = 'laravel-model';
                }

                // Prose-only tags are irrelevant; unknown tags promising types fail closed.
                if (preg_match('/(?:param|return|var|throws|method|property|type|template|extends|implements|use)/', $tag->name) === 1) {
                    $this->error($type, 'API-UNRESOLVED-TYPE', $path.'.'.$tag->name, 'Unsupported type tag');
                }

                continue;
            }
            $value = (new Doc\NodeTraverser([new Doc\NodeVisitor\CloningVisitor]))->traverse([$tag->value])[0];
            $this->walkDoc($type, $value, $path.'.'.$tag->name, $aliases, [], $node->getStartLine());

            if (property_exists($value, 'description')) {
                $value->description = '';
            }
            $out[$tag->name][] = trim((string) $value);
        }
        ksort($out);
        foreach ($out as $tag => &$values) {
            if (! str_contains($tag, 'template')) {
                sort($values);
            }
        }
        unset($values);

        return $out;
    }

    /** @param array<string, Doc\Type\TypeNode> $aliases
     * @param  list<string>  $visiting
     */
    private function walkDoc(SourceType $type, Doc\Node $node, string $path, array $aliases, array $visiting, int $line, bool $genericBase = false): void
    {
        if ($node instanceof Doc\Type\IdentifierTypeNode) {
            if (isset($aliases[$node->name])) {
                if (in_array($node->name, $visiting, true)) {
                    $this->error($type, 'API-UNRESOLVED-TYPE', $path, 'Cyclic type alias '.$node->name, $line);

                    return;
                }
                $alias = (new Doc\NodeTraverser([new Doc\NodeVisitor\CloningVisitor]))->traverse([$aliases[$node->name]])[0];
                $this->walkDoc($type, $alias, $path.'.alias('.$node->name.')', $aliases, [...$visiting, $node->name], $line);
                $node->name = (string) $alias;
            } elseif (($this->metadata[$type->name]['layer'] ?? '') === 'api' && (in_array($node->name, ['mixed', 'object'], true) || (! $genericBase && in_array($node->name, ['array', 'iterable', 'list', 'non-empty-list', 'non-empty-array'], true)))) {
                $this->error($type, 'API-UNTYPED-PAYLOAD', $path, 'Ambiguous nested payload '.$node->name, $line);
            } elseif (! in_array(strtolower($node->name), self::BUILTINS, true)) {
                $resolved = $type->resolve($node->name);
                $node->name = '\\'.$resolved;
                $this->payloads[$type->name][] = ['target' => $resolved, 'path' => $path, 'line' => $line];
            }

            return;
        }

        if ($node instanceof Doc\ConstExpr\ConstFetchNode && $node->className !== '') {
            $resolved = $type->resolve($node->className);
            $node->className = '\\'.$resolved;
            $this->payloads[$type->name][] = ['target' => $resolved, 'path' => $path.'.constant', 'line' => $line];
        }

        if ($node instanceof Doc\Type\InvalidTypeNode) {
            $this->error($type, 'API-UNRESOLVED-TYPE', $path, 'Invalid PHPDoc type', $line);

            return;
        }
        foreach (get_object_vars($node) as $key => $value) {
            // Shape keys are field labels, not references; alias origins are handled above.
            if (in_array($key, ['keyName', 'importedFrom'], true)) {
                continue;
            }

            if ($value instanceof Doc\Node) {
                $this->walkDoc($type, $value, $path.'.'.$key, $aliases, $visiting, $line, $node instanceof Doc\Type\GenericTypeNode && $key === 'type');
            } elseif (is_array($value)) {
                foreach ($value as $i => $child) {
                    if ($child instanceof Doc\Node) {
                        $this->walkDoc($type, $child, $path.'.'.$key.'.'.$i, $aliases, $visiting, $line);
                    }
                }
            }
        }
    }

    private function validateDeclaration(SourceType $type): void
    {
        $node = $type->node;
        $meta = $this->metadata[$type->name];
        $marked = in_array($meta['layer'], ['api', 'spi-implement', 'spi-call'], true);
        $propertyRecords = $this->records[$type->name]['properties'] ?? [];
        $publicData = is_array($propertyRecords) && array_any($propertyRecords, static fn (mixed $property): bool => is_array($property) && ($property['visibility'] ?? '') === 'public');
        $data = $meta['layer'] === 'api' && ($publicData || str_contains($type->name, '\\Data\\'));
        $event = $meta['layer'] === 'spi-call' && str_contains($type->name, '\\Events\\');

        if (($data || $event) && $node instanceof Node\Stmt\Class_ && (! $node->isReadonly() || ! $node->isFinal())) {
            $this->error($type, 'API-MUTABLE-DATA', 'declaration', 'Data and event payloads must be final readonly');
        }
        foreach ($this->payloads[$type->name] ?? [] as $reference) {
            if (! $this->visibleReference($type->name, $reference['path'], $meta['layer'] === 'spi-implement')) {
                continue;
            }
            $target = $this->lookup($reference['target']);

            if ($target === null && ! in_array($reference['target'], ['Closure', 'Throwable', 'Stringable', 'UnitEnum', 'BackedEnum', 'DateTimeImmutable', 'DateTimeInterface', 'stdClass', 'Attribute'], true)) {
                $this->error($type, 'API-UNRESOLVED-TYPE', $reference['path'], $reference['target'], $reference['line']);
            } elseif ($target !== null && $target->owner !== $type->owner && str_contains($target->name, '\\Internal\\') && $marked) {
                $this->error($type, 'API-INTERNAL-TYPE', $reference['path'], $target->name, $reference['line']);
            }

            if ($target !== null && $target->owner !== $type->owner && $target->owner !== '@vendor' && ($this->metadata[$target->name]['layer'] ?? '') === 'unclassified') {
                $this->error($type, 'API-IMPORT', $reference['path'], $target->name, $reference['line']);
            }
        }
        $bases = $this->parents($type);

        if ($node instanceof Node\Stmt\Class_ || $node instanceof Node\Stmt\Enum_) {
            foreach ($node->implements as $interface) {
                $bases[] = $interface->toString();
            }
        }
        foreach ($bases as $base) {
            $target = $this->lookup($base);

            if ($target !== null && $target->owner !== $type->owner && ($this->metadata[$target->name]['layer'] ?? '') === 'api') {
                $this->error($type, 'API-IMPLEMENT', 'extends/implements', $base);
            }
        }

        if (str_starts_with($meta['layer'], 'spi-') && ! $event) {
            foreach ($this->payloads[$type->name] ?? [] as $reference) {
                if ($this->isModelOrCollection($reference['target'], []) && (! str_contains($type->name, '\\Laravel\\') || ! str_contains($node->getDocComment()?->getText() ?? '', '@corex-model-extension'))) {
                    $this->error($type, 'API-MODEL-SPI', $reference['path'], 'Model extension requires Laravel placement and @corex-model-extension', $reference['line']);
                }
            }
        }

        if ($data || $event) {
            $properties = $this->records[$type->name]['properties'] ?? [];
            foreach (is_array($properties) ? $properties : [] as $name => $property) {
                if (! is_array($property)) {
                    continue;
                }
                $native = $property['type'] ?? null;
                $docs = json_encode($property['doc'] ?? [], JSON_THROW_ON_ERROR);

                if ($native === null || (is_string($native) && preg_match('/(?:^|[?(&|])(?:mixed|object)(?:$|[)|&])/', $native) === 1) || (is_string($native) && str_contains($native, 'array') && preg_match('/(?:array\s*[<{]|list\s*<)/', $docs) !== 1)) {
                    $this->error($type, 'API-UNTYPED-PAYLOAD', 'properties.'.$name, 'Payload needs a concrete scalar/list/shape/value type');
                }

                if (($property['static'] ?? false) === true || ($property['readonly'] ?? false) !== true) {
                    $this->error($type, 'API-MUTABLE-DATA', 'properties.'.$name, 'Mutable payload field');
                }
            }
        }
    }

    private function visibleReference(string $name, string $path, bool $protected): bool
    {
        if ($protected || preg_match('/(?:methods|properties|constants)\.([^ .]+)/', $path, $match) !== 1) {
            return true;
        }
        $category = str_contains($match[0], 'methods.') ? 'methods' : (str_contains($match[0], 'properties.') ? 'properties' : 'constants');
        $members = $this->records[$name][$category] ?? [];
        $member = is_array($members) ? ($members[$match[1]] ?? null) : null;

        return ! is_array($member) || ($member['visibility'] ?? 'public') === 'public';
    }

    /** @param list<string> $visited */
    private function walkPayload(SourceType $origin, string $name, array $visited, string $path): void
    {
        if (in_array($name, $visited, true)) {
            return;
        }
        $visited[] = $name;
        foreach ($this->payloads[$name] ?? [] as $reference) {
            if (! $this->visibleReference($name, $reference['path'], false)) {
                continue;
            }
            $targetName = $reference['target'];
            $target = $this->lookup($targetName);
            $next = $path.' → '.$reference['path'].' → '.$targetName;

            if (str_contains($targetName, '\\Internal\\')) {
                $this->error($origin, 'API-INTERNAL-TYPE', $next, $targetName, $reference['line']);
            }

            if (in_array($targetName, ['stdClass', 'DateTime', 'DateTimeInterface'], true) || ($target !== null && $target->owner === '@vendor' && ! str_contains($reference['path'], '@throws')) || $this->isModelOrCollection($targetName, []) || ($target !== null && $target->node instanceof Node\Stmt\Class_ && $target->node->getProperties() !== [] && (! $target->node->isFinal() || ! $target->node->isReadonly()))) {
                $this->error($origin, 'API-PAYLOAD', $next, 'Model, collection or mutable object payload: '.$targetName, $reference['line']);
            }

            if ($target !== null && $target->owner !== '@vendor') {
                $this->walkPayload($origin, $target->name, $visited, $next);
            }
        }
    }

    /** @param list<string> $visited */
    private function isModelOrCollection(string $name, array $visited): bool
    {
        if (in_array($name, ['Illuminate\\Database\\Eloquent\\Model', 'Illuminate\\Support\\Collection', 'Illuminate\\Support\\LazyCollection', 'Illuminate\\Database\\Eloquent\\Collection'], true)) {
            return true;
        }

        if (in_array($name, $visited, true)) {
            return false;
        }
        $type = $this->lookup($name);

        if ($type === null) {
            return false;
        }
        foreach ($this->parents($type) as $parent) {
            if ($this->isModelOrCollection($parent, [...$visited, $name])) {
                return true;
            }
        }

        return false;
    }

    private function validateReferences(string $root): void
    {
        $edges = [];
        $config = $root.'/scripts/package-graph-gate.json';

        if (is_file($config)) {
            $data = json_decode($this->read($config), true, flags: JSON_THROW_ON_ERROR);

            if (! is_array($data) || ! is_array($data['internal_composition_edges'] ?? null)) {
                throw new RuntimeException('Invalid package graph composition policy');
            }
            $edges = $data['internal_composition_edges'];

            if (! array_is_list($edges)) {
                throw new RuntimeException('Composition edges must be a list');
            }
            foreach ($edges as $edge) {
                foreach (['from', 'to', 'import_file', 'symbol', 'target_file'] as $key) {
                    if (! is_array($edge) || ! is_string($edge[$key] ?? null) || $edge[$key] === '' || str_contains($edge[$key], '*') || str_contains($edge[$key], '..')) {
                        throw new RuntimeException('Invalid finite composition edge');
                    }
                }

                if (! is_file($root.'/'.$edge['import_file']) || ! is_file($root.'/'.$edge['target_file'])) {
                    throw new RuntimeException('Composition edge source is missing');
                }
            }
        }
        foreach ($this->references as $reference) {
            $target = $this->types[$this->names[strtolower($reference['name'])] ?? ''] ?? null;

            if ($target === null || $target->owner === $reference['owner'] || $target->owner === '@vendor') {
                continue;
            }
            $internal = str_contains($target->name, '\\Internal\\');
            $allowed = false;
            foreach ($edges as $edge) {
                if ($edge['import_file'] === $reference['file'] && $edge['symbol'] === $target->name && $edge['from'] === $reference['owner'] && $edge['to'] === $target->owner && $edge['target_file'] === $target->file) {
                    $allowed = true;
                }
            }

            if (($internal && ! $allowed) || ($this->metadata[$target->name]['layer'] ?? '') === 'unclassified') {
                $this->diagnostics[] = new SurfaceDiagnostic($internal ? 'API-INTERNAL-TYPE' : 'API-IMPORT', $reference['file'], $reference['line'], $target->name, 'reference', 'Cross-owner reference from '.$reference['owner']);
            }
        }
    }
}
