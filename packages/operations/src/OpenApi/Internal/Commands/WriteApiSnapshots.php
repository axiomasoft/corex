<?php

declare(strict_types=1);

namespace CoreX\Operations\OpenApi\Internal\Commands;

use CoreX\Operations\Definition\Contracts\OperationRegistry;
use CoreX\Operations\Laravel\Support\ApiOptions;
use CoreX\Operations\OpenApi\Contracts\ContractSnapshotSection;
use CoreX\Operations\OpenApi\Internal\ContractSnapshotBuilder;
use CoreX\Operations\OpenApi\Internal\OpenApiDocumentGenerator;
use CoreX\Operations\Versioning\Contracts\ApiProfiles as ApiProfileFactory;
use Illuminate\Console\Command;
use Packman\Attributes\Internal;
use Packman\Errors\Contracts\ProblemRegistry;
use Packman\Modules\Contracts\Registry\ModuleRegistry;
use RuntimeException;

/** @internal Writes the installed-code v2 contract snapshot. */
#[Internal]
final class WriteApiSnapshots extends Command
{
    protected $signature = 'corex:operations:snapshot {directory : Destination for the v2 JSON carriers} {--check : Compare without writing} {--major=v2 : Required snapshot major}';

    protected $description = 'Write or check the deterministic v2 API contract snapshot';

    public function handle(
        OperationRegistry $operations,
        OpenApiDocumentGenerator $openApi,
        ApiProfileFactory $profiles,
        ApiOptions $options,
        ModuleRegistry $modules,
        ProblemRegistry $problems,
    ): int {
        if ($this->option('major') !== 'v2') {
            $this->error('Only the v2 snapshot can be generated.');

            return self::INVALID;
        }

        $sections = [];

        foreach ($this->laravel->tagged(ContractSnapshotSection::class) as $section) {
            if (! $section instanceof ContractSnapshotSection) {
                throw new RuntimeException('Snapshot section must implement ContractSnapshotSection.');
            }

            $sections[] = $section;
        }

        $builder = new ContractSnapshotBuilder(operations: $operations, openApi: $openApi, profile: $profiles->make(registry: $modules->compiled(), options: $options), problems: $problems, serverUrl: $options->publicBaseUri(), sections: $sections);

        $directory = $this->argument('directory');

        if (! is_string($directory) || $directory === '') {
            throw new RuntimeException('Snapshot directory is required.');
        }

        $documents = $builder->documents();
        $paths = $this->paths(directory: $directory, files: ContractSnapshotBuilder::FILES);

        if ($this->option('check') === true) {
            if (! is_dir($directory)) {
                $this->error('Snapshot directory is missing.');

                return 1;
            }

            if (! $this->hasOnlyCarriers(directory: $directory, files: ContractSnapshotBuilder::FILES)) {
                $this->error('Snapshot directory contains unexpected entries.');

                return 1;
            }
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

        if (! $this->hasOnlyCarriers(directory: $directory, files: ContractSnapshotBuilder::FILES)) {
            throw new RuntimeException('Snapshot directory contains unexpected entries.');
        }
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

    /** @param list<string> $files
     * @return array<string, string> */
    private function paths(string $directory, array $files): array
    {
        $paths = [];

        foreach ($files as $name) {
            $paths[$name] = rtrim($directory, '/').'/'.$name;
        }

        return $paths;
    }

    /** @param list<string> $files */
    private function hasOnlyCarriers(string $directory, array $files): bool
    {
        $entries = scandir($directory);

        return $entries !== false && array_diff($entries, ['.', '..', ...$files]) === [];
    }
}
