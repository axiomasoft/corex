<?php

declare(strict_types=1);

namespace CoreX\Api\Commands;

use CoreX\Api\Internal\ApiProfileFactory;
use CoreX\Api\Internal\ContractSnapshotBuilder;
use CoreX\Api\OpenApiDocumentGenerator;
use CoreX\Api\OperationRegistry;
use CoreX\Api\Support\ApiOptions;
use CoreX\Modules\Contracts\ModuleRegistry;
use Illuminate\Console\Command;
use RuntimeException;

/** @internal Writes the installed-code v1 contract snapshot. It does not accept tenant state. */
final class WriteApiSnapshots extends Command
{
    protected $signature = 'corex:api-snapshots {directory : Destination for the v1 JSON carriers} {--check : Compare without writing} {--fixture= : P12 public-error contract PHP file} {--major=v1}';

    protected $description = 'Write or check the deterministic v1 API contract snapshot';

    public function handle(
        OperationRegistry $operations,
        OpenApiDocumentGenerator $openApi,
        ApiProfileFactory $profiles,
        ApiOptions $options,
        ModuleRegistry $modules,
    ): int {
        $builder = new ContractSnapshotBuilder($operations, $openApi, $profiles->make($modules->compiled(), $options));

        $directory = $this->argument('directory');
        $major = $this->option('major');

        if (! is_string($directory) || $directory === '' || ! is_string($major) || $major === '') {
            throw new RuntimeException('Snapshot directory and major are required.');
        }

        $fixture = $this->option('fixture');
        $contracts = $this->contracts(is_string($fixture) ? $fixture : null);
        $documents = $builder->documents($contracts, $major);
        $paths = $this->paths($directory);

        if ($this->option('check')) {
            foreach ($documents as $name => $bytes) {
                $current = is_file($paths[$name]) ? file_get_contents($paths[$name]) : false;

                if ($current !== $bytes) {
                    $this->error($name.' drifted');

                    return 1;
                }
            }

            return 0;
        }

        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException('Snapshot directory cannot be created.');
        }

        $this->assertClean($directory);
        $temporary = [];

        try {
            foreach ($documents as $name => $bytes) {
                $temporary[$name] = $directory.'/.'.$name.'.'.bin2hex(random_bytes(6)).'.tmp';

                if (file_put_contents($temporary[$name], $bytes) === false) {
                    throw new RuntimeException('Snapshot write failed.');
                }
            }

            foreach ($documents as $name => $bytes) {
                if (! rename($temporary[$name], $paths[$name])) {
                    throw new RuntimeException('Snapshot replace failed.');
                }

                unset($temporary[$name]);
            }
        } finally {
            foreach ($temporary as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }

        return 0;
    }

    /** @return list<array<string, mixed>> */
    private function contracts(?string $fixture): array
    {
        if ($fixture === null || $fixture === '') {
            throw new RuntimeException('P12 error fixture is required.');
        }

        $loaded = require $fixture;

        if (! is_array($loaded) || ! is_array($loaded['contracts'] ?? null)) {
            throw new RuntimeException('P12 error fixture is unreadable.');
        }

        return $loaded['contracts'];
    }

    /** @return array<string, string> */
    private function paths(string $directory): array
    {
        $paths = [];

        foreach (ContractSnapshotBuilder::FILES as $name) {
            $paths[$name] = rtrim($directory, '/').'/'.$name;
        }

        return $paths;
    }

    private function assertClean(string $directory): void
    {
        $allowed = array_flip(ContractSnapshotBuilder::FILES);

        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            if (! isset($allowed[$entry])) {
                throw new RuntimeException('Snapshot directory is dirty.');
            }
        }
    }
}
