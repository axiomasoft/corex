<?php

declare(strict_types=1);

namespace CoreX\Foundation\Internal;

use Composer\InstalledVersions;
use CoreX\Foundation\Data\ModuleRef;
use CoreX\Foundation\Exceptions\LifecycleBlocked;
use Illuminate\Contracts\Foundation\Application;
use Packman\Attributes\Internal;

#[Internal]
final readonly class LifecycleCatalogue
{
    public function __construct(private Application $app) {}

    /** @return non-empty-list<string> */
    public function packages(ModuleRef $first, ModuleRef ...$rest): array
    {
        $packages = [];
        foreach ([$first, ...$rest] as $ref) {
            if (! InstalledVersions::isInstalled($ref->package) && ! isset($this->composition()[$ref->package])) {
                throw new LifecycleBlocked('Uninstalled reference owner '.$ref->package);
            }
            $packages[] = $ref->package;
        }
        $packages = array_values(array_unique($packages));
        sort($packages, SORT_STRING);

        return $packages;
    }

    /** @param array<string,array<string,mixed>> $composition
     * @return list<string>
     */
    public function expectedOwners(array $composition): array
    {
        $owners = ['axiomasoft/corex'];
        foreach (['access', 'tenancy', 'custom-fields', 'import-export', 'intl', 'media', 'notifications', 'operations', 'recurring-tasks', 'scheduler', 'search', 'tasks', 'webhooks', 'workflow'] as $suffix) {
            $owner = 'axiomasoft/corex-'.$suffix;

            if (isset($composition[$owner])) {
                $owners[] = $owner;
            }
        }

        if (isset($composition['flex/crm'])) {
            $owners[] = 'flex/crm';
        }

        if (isset($composition['axiomasoft/packman-panels'])) {
            $owners[] = 'axiomasoft/packman-panels';
        }

        return $owners;
    }

    /** @return array<string,array<string,mixed>> */
    public function composition(): array
    {
        $path = $this->app->basePath('vendor/composer/installed.json');
        $contents = is_readable($path) ? file_get_contents($path) : false;
        $document = is_string($contents) ? json_decode($contents, true, flags: JSON_THROW_ON_ERROR) : null;

        if (! is_array($document) || ! is_array($document['packages'] ?? null)) {
            throw new LifecycleBlocked('Installed Composer composition unavailable.');
        }
        $map = [];
        foreach ($document['packages'] as $record) {
            if (! is_array($record) || ! is_string($record['name'] ?? null) || ! is_string($record['version'] ?? null)) {
                throw new LifecycleBlocked('Incomplete installed Composer declaration.');
            }
            $map[$record['name']] = LifecycleCheckpoint::map($record);
        }
        ksort($map, SORT_STRING);

        return $map;
    }
}
