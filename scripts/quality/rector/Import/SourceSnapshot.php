<?php

declare(strict_types=1);

namespace CoreX\Tooling\Rector\Import;

use RuntimeException;

final readonly class SourceSnapshot
{
    /**
     * @param  array<string, array{git_blob: string, sha256: string, text: string}>  $files
     * @param  list<string>  $production
     * @param  list<string>  $configs
     */
    private function __construct(public array $files, public array $production, public array $configs) {}

    public static function load(string $path): self
    {
        $text = file_get_contents($path);

        if ($text === false) {
            throw new RuntimeException('Cannot read source snapshot: '.$path);
        }

        $document = json_decode($text, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($document) || ($document['schema'] ?? null) !== 1 || ($document['commit'] ?? null) !== 'c0f9a2ff7365cec45d4dc64223608e7cec74604a' || ($document['repository'] ?? null) !== 'https://github.com/driftingly/rector-laravel' || ! is_array($document['files'] ?? null)) {
            throw new RuntimeException('Invalid pinned source snapshot');
        }

        $files = [];

        foreach ($document['files'] as $name => $file) {
            if (! is_string($name) || str_contains($name, '..') || str_starts_with($name, '/') || ! is_array($file) || ! is_string($file['text'] ?? null) || ! is_string($file['sha256'] ?? null) || ! is_string($file['git_blob'] ?? null)) {
                throw new RuntimeException('Invalid source file record');
            }

            $content = $file['text'];

            if (hash('sha256', $content) !== $file['sha256'] || hash('sha1', 'blob '.strlen($content)."\0".$content) !== $file['git_blob']) {
                throw new RuntimeException('Pinned input hash mismatch: '.$name);
            }

            $files[$name] = ['git_blob' => $file['git_blob'], 'sha256' => $file['sha256'], 'text' => $content];
        }

        $production = self::paths($document['production'] ?? null, $files);
        $configs = self::paths($document['configs'] ?? null, $files);

        if (count($production) !== 46 || count($configs) !== 4) {
            throw new RuntimeException('Incomplete selected production closure');
        }

        return new self($files, $production, $configs);
    }

    /**
     * @param  array<string, array{git_blob: string, sha256: string, text: string}>  $files
     * @return list<string>
     */
    private static function paths(mixed $value, array $files): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            throw new RuntimeException('Invalid source path list');
        }

        $paths = [];

        foreach ($value as $path) {
            if (! is_string($path) || ! isset($files[$path]) || in_array($path, $paths, true)) {
                throw new RuntimeException('Missing or duplicate source closure path');
            }

            $paths[] = $path;
        }

        return $paths;
    }
}
