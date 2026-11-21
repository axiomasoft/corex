<?php

declare(strict_types=1);

namespace App\OwnerEffects;

use CoreX\Storage\Contracts\TenantStorage;
use CoreX\Storage\Data\ExportArchiveRef;
use CoreX\Storage\Data\StorageCapabilities;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Str;
use PharData;

/** Private host storage for a real, published transfer result in the isolation test. */
final readonly class ResultStorage implements TenantStorage
{
    public function __construct(private FilesystemManager $filesystems) {}

    private function open(string $area): FilesystemAdapter
    {
        $scope = Effects::scope();
        $root = storage_path('app/private/result-storage/'.Effects::identity($scope).'/'.$area);

        return $this->filesystems->build(['driver' => 'local', 'root' => $root, 'visibility' => 'private', 'links' => 'disallow', 'throw' => true]);
    }

    public function disk(): Filesystem
    {
        return $this->open('parts');
    }

    public function ephemeralDisk(): Filesystem
    {
        return $this->open('exports');
    }

    public function capabilities(): StorageCapabilities
    {
        return new StorageCapabilities(usage: true, export: true);
    }

    public function usageBytes(): int
    {
        $disk = $this->disk();

        return array_sum(array_map($disk->size(...), $disk->allFiles()));
    }

    public function exportArchive(): ExportArchiveRef
    {
        $disk = $this->open('parts');
        $archives = $this->open('exports');
        $archives->makeDirectory('archives');
        $name = 'archives/'.Str::uuid7().'.tar';
        $tar = new PharData($archives->path($name));
        foreach ($disk->allFiles() as $path) {
            $tar->addFile($disk->path($path), $path);
        }
        unset($tar);

        return new ExportArchiveRef($name, $archives->size($name), now()->toDateTimeImmutable());
    }
}
