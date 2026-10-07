<?php

declare(strict_types=1);

namespace CoreX\Storage\Internal;

use Closure;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Storage\Exceptions\StorageOperationFailed;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use RuntimeException;

final readonly class ScopedFilesystem implements Filesystem
{
    public function __construct(
        private Filesystem $filesystem,
        private TenantContextResolver $contexts,
        private string $accountId,
        private string $root,
        private bool $localPaths = true,
    ) {}

    public function path($path)
    {
        $path = $this->safePath($path);
        $this->assertCurrentContext();

        if (! $this->localPaths) {
            throw new StorageOperationFailed('The scoped object storage driver does not expose local paths.');
        }

        $arguments = [$path];

        return $this->call(method: 'path', arguments: $arguments, operation: fn () => $this->filesystem->path(...$arguments));
    }

    public function exists($path)
    {
        $arguments = [$this->safePath($path)];

        return $this->call(method: 'exists', arguments: $arguments, operation: fn () => $this->filesystem->exists(...$arguments));
    }

    public function get($path)
    {
        $arguments = [$this->safePath($path)];

        return $this->call(method: 'get', arguments: $arguments, operation: fn () => $this->filesystem->get(...$arguments));
    }

    public function readStream($path)
    {
        $arguments = [$this->safePath($path)];

        return $this->call(method: 'readStream', arguments: $arguments, operation: fn () => $this->filesystem->readStream(...$arguments));
    }

    public function put($path, $contents, $options = [])
    {
        $arguments = [$this->safePath($path), $contents, $options];

        return $this->call(method: 'put', arguments: $arguments, operation: fn () => $this->filesystem->put(...$arguments));
    }

    /**
     * @param  File|UploadedFile|string|array<mixed>|null  $file
     */
    public function putFile($path, $file = null, $options = [])
    {
        if ($file === null || is_array($file)) {
            [$path, $file, $options] = ['', $path, $file ?? []];
        }

        $arguments = [$this->safePath($path), $file, $options];

        return $this->call(method: 'putFile', arguments: $arguments, operation: fn () => $this->filesystem->putFile(...$arguments));
    }

    /**
     * @param  File|UploadedFile|string|array<mixed>|null  $file
     * @param  string|array<mixed>|null  $name
     */
    public function putFileAs($path, $file, $name = null, $options = [])
    {
        if ($name === null || is_array($name)) {
            [$path, $file, $name, $options] = ['', $path, $file, $name ?? []];
        }

        $arguments = [$this->safePath($path), $file, $this->safePath($name), $options];

        return $this->call(method: 'putFileAs', arguments: $arguments, operation: fn () => $this->filesystem->putFileAs(...$arguments));
    }

    /** @param array<mixed> $options */
    public function writeStream($path, $resource, array $options = [])
    {
        $arguments = [$this->safePath($path), $resource, $options];

        return $this->call(method: 'writeStream', arguments: $arguments, operation: fn () => $this->filesystem->writeStream(...$arguments));
    }

    public function getVisibility($path)
    {
        $arguments = [$this->safePath($path)];

        return $this->call(method: 'getVisibility', arguments: $arguments, operation: fn () => $this->filesystem->getVisibility(...$arguments));
    }

    public function setVisibility($path, $visibility)
    {
        $arguments = [$this->safePath($path), $visibility];

        return $this->call(method: 'setVisibility', arguments: $arguments, operation: fn () => $this->filesystem->setVisibility(...$arguments));
    }

    public function prepend($path, $data)
    {
        $arguments = [$this->safePath($path), $data];

        return $this->call(method: 'prepend', arguments: $arguments, operation: fn () => $this->filesystem->prepend(...$arguments));
    }

    public function append($path, $data)
    {
        $arguments = [$this->safePath($path), $data];

        return $this->call(method: 'append', arguments: $arguments, operation: fn () => $this->filesystem->append(...$arguments));
    }

    /** @param string|array<mixed> $paths */
    public function delete($paths)
    {
        $arguments = [is_array($paths) ? array_map($this->safePath(...), $paths) : $this->safePath($paths)];

        return $this->call(method: 'delete', arguments: $arguments, operation: fn () => $this->filesystem->delete(...$arguments));
    }

    public function copy($from, $to)
    {
        $arguments = [$this->safePath($from), $this->safePath($to)];

        return $this->call(method: 'copy', arguments: $arguments, operation: fn () => $this->filesystem->copy(...$arguments));
    }

    public function move($from, $to)
    {
        $arguments = [$this->safePath($from), $this->safePath($to)];

        return $this->call(method: 'move', arguments: $arguments, operation: fn () => $this->filesystem->move(...$arguments));
    }

    public function size($path)
    {
        $arguments = [$this->safePath($path)];

        return $this->call(method: 'size', arguments: $arguments, operation: fn () => $this->filesystem->size(...$arguments));
    }

    public function lastModified($path)
    {
        $arguments = [$this->safePath($path)];

        return $this->call(method: 'lastModified', arguments: $arguments, operation: fn () => $this->filesystem->lastModified(...$arguments));
    }

    public function files($directory = null, $recursive = false)
    {
        $arguments = [$directory === null ? null : $this->safePath($directory), $recursive];

        return $this->call(method: 'files', arguments: $arguments, operation: fn () => $this->filesystem->files(...$arguments));
    }

    public function allFiles($directory = null)
    {
        $arguments = [$directory === null ? null : $this->safePath($directory)];

        return $this->call(method: 'allFiles', arguments: $arguments, operation: fn () => $this->filesystem->allFiles(...$arguments));
    }

    public function directories($directory = null, $recursive = false)
    {
        $arguments = [$directory === null ? null : $this->safePath($directory), $recursive];

        return $this->call(method: 'directories', arguments: $arguments, operation: fn () => $this->filesystem->directories(...$arguments));
    }

    public function allDirectories($directory = null)
    {
        $arguments = [$directory === null ? null : $this->safePath($directory)];

        return $this->call(method: 'allDirectories', arguments: $arguments, operation: fn () => $this->filesystem->allDirectories(...$arguments));
    }

    public function makeDirectory($path)
    {
        $arguments = [$this->safePath($path)];

        return $this->call(method: 'makeDirectory', arguments: $arguments, operation: fn () => $this->filesystem->makeDirectory(...$arguments));
    }

    public function deleteDirectory($directory)
    {
        $arguments = [$this->safePath($directory)];

        return $this->call(method: 'deleteDirectory', arguments: $arguments, operation: fn () => $this->filesystem->deleteDirectory(...$arguments));
    }

    public function url(string $path): string
    {
        $this->safePath($path);
        $this->assertCurrentContext();

        throw new StorageOperationFailed('The scoped storage driver does not expose file URLs.');
    }

    /**
     * @template T
     *
     * @param  array<mixed>  $arguments
     * @param  Closure(): T  $operation
     * @return T
     */
    private function call(string $method, array $arguments, Closure $operation): mixed
    {
        $this->assertCurrentContext();
        $this->assertNoSymlink(path: '');

        if (! $this->localPaths) {
            $optionIndex = match ($method) {
                'put', 'putFile', 'writeStream' => 2,
                'putFileAs' => 3,
                default => null,
            };
            $options = $optionIndex === null ? [] : ($arguments[$optionIndex] ?? []);
            $visibility = $method === 'setVisibility' ? $arguments[1] : (is_array($options) ? ($options['visibility'] ?? 'private') : $options);

            if ($visibility !== 'private' || (is_array($options) && ($options['ACL'] ?? 'private') !== 'private')) {
                throw new StorageOperationFailed('Scoped object storage supports private visibility only.');
            }
        }

        try {
            return $operation();
        } catch (RuntimeException $exception) {
            throw new StorageOperationFailed('Scoped storage operation failed.', previous: $exception);
        }
    }

    private function assertCurrentContext(): void
    {
        if ($this->contexts->current()->account->id !== $this->accountId) {
            throw new StorageOperationFailed('Storage handle belongs to a different account context.');
        }
    }

    private function safePath(mixed $path): string
    {
        if (! is_string($path) || str_contains($path, "\0") || str_contains($path, '\\')) {
            throw new StorageOperationFailed('Storage path is invalid.');
        }

        $decoded = $path;
        do {
            $previous = $decoded;
            $decoded = rawurldecode($decoded);
        } while ($decoded !== $previous);

        if (str_contains($decoded, "\0") || str_contains($decoded, '\\')) {
            throw new StorageOperationFailed('Storage path is invalid.');
        }

        if (str_starts_with($decoded, '/') || preg_match('/^[A-Za-z]:/', $decoded) === 1) {
            throw new StorageOperationFailed('Storage path must be relative.');
        }

        foreach (explode('/', $decoded) as $segment) {
            if ($segment === '..') {
                throw new StorageOperationFailed('Storage path traversal is forbidden.');
            }
        }

        $this->assertNoSymlink(path: $decoded);

        return $decoded;
    }

    private function assertNoSymlink(string $path): void
    {
        if (! $this->localPaths) {
            return;
        }

        $candidate = rtrim($this->root, DIRECTORY_SEPARATOR);

        for ($ancestor = $candidate; $ancestor !== '' && $ancestor !== dirname($ancestor); $ancestor = dirname($ancestor)) {
            if (is_link($ancestor)) {
                throw new StorageOperationFailed('Storage roots must not contain symbolic links.');
            }
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '') {
                continue;
            }

            $candidate .= DIRECTORY_SEPARATOR.$segment;

            if (is_link($candidate)) {
                throw new StorageOperationFailed('Storage paths must not contain symbolic links.');
            }
        }
    }
}
