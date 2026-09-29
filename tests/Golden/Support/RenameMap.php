<?php

declare(strict_types=1);

namespace CoreX\Tests\Golden\Support;

use RuntimeException;

/**
 * Projects both the frozen baseline and the current snapshot using exact tokens.
 * Prefix replacement would be wrong: psr-4 folding maps one old prefix to many new ones.
 */
final readonly class RenameMap
{
    private const FQCN = '/\\\\?[A-Za-z_]\w*(?:\\\\[A-Za-z_]\w*)+/';

    /** @param array<string, string> $classes @param array<string, string> $packages @param list<array<string, mixed>> $manual */
    private function __construct(private array $classes, private array $packages, private array $manual) {}

    public static function load(string $path): self
    {
        $data = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $classes = $packages = [];
        foreach ($data['generated'] as $rule) {
            match ($rule['kind']) {
                'class' => $classes[$rule['from']] = $rule['to'],
                'package' => $packages[$rule['from']] = $rule['to'],
            };
        }

        foreach ($data['manual'] as $rule) {
            if ($rule['kind'] === 'identifier' && str_contains($rule['from'], '\\')) {
                $classes[$rule['from']] = $rule['to'];
            }
        }

        $projectionPath = dirname($path).'/package-projection.json';

        if (is_file($projectionPath)) {
            $projection = json_decode((string) file_get_contents($projectionPath), true, flags: JSON_THROW_ON_ERROR);
            foreach ($projection as $old => $target) {
                if (! in_array($target, $data['ambiguous_packages'][$old] ?? [], true)) {
                    throw new RuntimeException('Invalid primary package projection.');
                }
                $packages[$old] = $target;
            }
        }

        return new self($classes, $packages, $data['manual']);
    }

    public function apply(mixed $value): mixed
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $key => $item) {
                $out[is_string($key) ? $this->string($key) : $key] = $this->apply($item);
            }

            return $out;
        }

        return is_string($value) ? $this->string($value) : $value;
    }

    private function string(string $value): string
    {
        if (isset($this->packages[$value])) {
            return $this->packages[$value];
        }

        $value = preg_replace_callback(self::FQCN, function (array $m): string {
            $leading = str_starts_with($m[0], '\\') ? '\\' : '';
            $name = ltrim($m[0], '\\');

            return $leading.($this->classes[$name] ?? $name);
        }, $value);

        foreach ($this->manual as $rule) {
            // Exact identifier rules are added by subsequent identifier migrations.
            if (($rule['kind'] ?? null) === 'identifier' && $value === $rule['from']) {
                return $rule['to'];
            }
        }

        return $value;
    }
}
