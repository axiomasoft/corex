<?php

declare(strict_types=1);

namespace App\BulkTransfer;

use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Storage\Contracts\TenantStorage;
use CoreX\Storage\Data\ExportArchiveRef;
use CoreX\Storage\Data\StorageCapabilities;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;
use LogicException;

final readonly class PrivateStorage implements TenantStorage
{
    public function __construct(private TenantContextResolver $tenant, private FilesystemManager $filesystems) {}

    public function disk(): Filesystem
    {
        return $this->open('durable');
    }

    public function ephemeralDisk(): Filesystem
    {
        return $this->open('ephemeral');
    }

    public function capabilities(): StorageCapabilities
    {
        return new StorageCapabilities(usage: false, export: true);
    }

    public function usageBytes(): int
    {
        throw new LogicException('This tenant disk does not expose usage.');
    }

    public function exportArchive(): ExportArchiveRef
    {
        throw new LogicException('This tenant disk stores export parts.');
    }

    public static function root(): string
    {
        return storage_path('app/private/bulk-transfer');
    }

    private function open(string $namespace): Filesystem
    {
        $settings = ['driver' => 'local', 'root' => self::root().'/'.$this->tenant->current()->id().'/'.$namespace,
            'visibility' => 'private', 'throw' => true, 'links' => 'disallow'];
        $disk = $this->filesystems->build($settings);

        return new class($disk->getDriver(), $disk->getAdapter(), $settings) extends FilesystemAdapter
        {
            public function put($path, $contents, $options = [])
            {
                $written = parent::put($path, $contents, $options);

                if ($written && is_string($contents)) {
                    $receipt = ['ref' => $path, 'sha256' => hash('sha256', $contents), 'bytes' => strlen($contents),
                        'visibility' => $this->getVisibility($path)];
                    Journal::record('private-put', $receipt);

                    if (getenv('COREX_BULK_MODE') === 'export-stage') {
                        BarrierWitness::beforeKill('export-part-committed', 'export', $receipt);
                    }
                }

                return $written;
            }

            public function get($path)
            {
                $content = parent::get($path);

                if (is_string($content)) {
                    Journal::record('private-read', ['ref' => $path, 'sha256' => hash('sha256', $content), 'bytes' => strlen($content)]);
                }

                return $content;
            }
        };
    }
}
