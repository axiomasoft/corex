<?php

declare(strict_types=1);

namespace CoreX\Operations\Tests\Feature;

use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Modules\Data\Api;
use CoreX\Modules\Data\CompiledRegistry;
use CoreX\Modules\Data\InstalledPackageVersion;
use CoreX\Modules\Data\ModuleManifest;
use CoreX\Modules\Internal\Compiler\RegistryCompiler;
use CoreX\Operations\OpenApi\Contracts\ContractSnapshotSection;
use CoreX\Operations\OpenApi\Internal\ContractSnapshotBuilder;
use CoreX\Operations\Testing\ModuleFixture;
use CoreX\Operations\Testing\NoteHandler;
use CoreX\Operations\Tests\TestCase;

final class ContractSnapshotTest extends TestCase
{
    public function test_generation_is_byte_stable_and_check_detects_drift(): void
    {
        $fixture = dirname(__DIR__, 4).'/tests/fixtures/public-error-contract.php';
        $this->app->instance(ModuleRegistry::class, new ModuleFixture($this->compile(false)));
        $first = sys_get_temp_dir().'/corex-api-snapshot-a-'.bin2hex(random_bytes(4));
        $second = sys_get_temp_dir().'/corex-api-snapshot-b-'.bin2hex(random_bytes(4));
        $this->artisan('corex:operations:snapshot', ['directory' => $first, '--fixture' => $fixture])->assertSuccessful();
        $this->artisan('corex:operations:snapshot', ['directory' => $second, '--fixture' => $fixture])->assertSuccessful();
        $this->assertSame($this->files($first), $this->files($second));
        $this->assertSame(hash('sha256', $this->files($first)['errors.json']), json_decode($this->files($first)['manifest.json'], true)['files']['errors.json']);
        $this->assertSame(hash('sha256', $this->files($first)['openapi.json']), json_decode($this->files($first)['manifest.json'], true)['files']['openapi.json']);
        $this->assertSame(hash('sha256', $this->files($first)['operations.json']), json_decode($this->files($first)['manifest.json'], true)['files']['operations.json']);
        $this->assertSame(hash('sha256', $this->files($first)['webhooks.json']), json_decode($this->files($first)['manifest.json'], true)['files']['webhooks.json']);
        $webhooks = json_decode($this->files($first)['webhooks.json'], true);
        $this->assertSame('corex-webhook-contract/1', $webhooks['schema']);
        $this->assertSame([], $webhooks['topics']);
        $this->assertSame('at-least-once', $webhooks['delivery']['guarantee']);
        $this->assertSame('none', $webhooks['delivery']['ordering']);
        $this->assertSame('delivery-id', $webhooks['rotation']['id_semantics']);
        $this->assertSame(20480, $webhooks['payload_limit_bytes']);
        $operations = json_decode($this->files($first)['operations.json'], true);
        $created = array_values(array_filter($operations, static fn (array $row): bool => $row['id'] === 'notes.create'))[0];
        $this->assertSame('enforce', $created['cost']['enforcement']['global']);
        $this->assertSame('observe', $created['cost']['enforcement']['account']);
        $errors = json_decode($this->files($first)['errors.json'], true);
        $codes = array_column($errors, 'code');
        $this->assertContains('operations.bulk_not_ready', $codes);
        $this->assertContains('webhooks.webhook_replay_expired', $codes);

        $this->app->instance(ModuleRegistry::class, new ModuleFixture($this->compile(true)));
        $reversed = sys_get_temp_dir().'/corex-api-snapshot-c-'.bin2hex(random_bytes(4));
        $this->artisan('corex:operations:snapshot', ['directory' => $reversed, '--fixture' => $fixture])->assertSuccessful();
        $this->assertSame($this->files($first), $this->files($reversed));
        $changed = sys_get_temp_dir().'/corex-api-snapshot-e-'.bin2hex(random_bytes(4));
        $this->app->instance(ModuleRegistry::class, new ModuleFixture($this->compile(false, 'notes.changed')));
        $this->artisan('corex:operations:snapshot', ['directory' => $changed, '--fixture' => $fixture])->assertSuccessful();
        $this->assertNotSame($this->files($first)['manifest.json'], $this->files($changed)['manifest.json']);
        $this->app->instance(ModuleRegistry::class, new ModuleFixture($this->compile(false)));

        $drift = sys_get_temp_dir().'/corex-api-snapshot-d-'.bin2hex(random_bytes(4));
        mkdir($drift);
        foreach ($this->files($first) as $name => $bytes) {
            file_put_contents($drift.'/'.$name, $name === 'operations.json' ? $bytes.' ' : $bytes);
        }
        $this->artisan('corex:operations:snapshot', ['directory' => $drift, '--fixture' => $fixture, '--check' => true])->assertExitCode(1);
        $this->artisan('corex:operations:snapshot', ['directory' => $first, '--fixture' => $fixture, '--check' => true])->assertSuccessful();
        $committed = dirname(__DIR__, 4).'/api/v1';
        $this->app->instance(ModuleRegistry::class, new ModuleFixture($this->compile(false)));

        if (! is_file($committed.'/manifest.json')) {
            $this->artisan('corex:operations:snapshot', ['directory' => $committed, '--fixture' => $fixture])->assertSuccessful();
        }
        $this->artisan('corex:operations:snapshot', ['directory' => $committed, '--fixture' => $fixture, '--check' => true])->assertSuccessful();

        $joined = implode("\n", $this->files($first));
        $this->assertStringNotContainsString('/home/', $joined);
        $this->assertStringNotContainsString('BEGIN PRIVATE', $joined);
        $this->assertArrayHasKey('webhooks.json', $this->files($first));
        $this->assertSame(['errors.json', 'manifest.json', 'openapi.json', 'operations.json', 'webhooks.json'], array_keys($this->files($first)));
    }

    /** @return array<string, string> */
    private function files(string $directory): array
    {
        $files = [];

        foreach ([...ContractSnapshotBuilder::FILES, ...array_map(static fn ($section): string => $section->file(), [...$this->app->tagged(ContractSnapshotSection::class)])] as $name) {
            $files[$name] = (string) file_get_contents($directory.'/'.$name);
        }

        ksort($files);

        return $files;
    }

    private function compile(bool $reversed, string $createId = 'notes.create'): CompiledRegistry
    {
        $declarations = [
            Api::operation(
                id: $createId, method: 'POST', path: '/notes', handler: NoteHandler::class, ability: 'notes.read',
                requestSchema: ['type' => 'object'], responseSchema: ['type' => 'object'], successStatus: 201,
            ),
            Api::operation(
                id: 'notes.read', method: 'GET', path: '/notes/{id}', handler: NoteHandler::class, ability: 'notes.read',
                requestSchema: ['type' => 'object'], responseSchema: ['type' => 'object'],
            ),
        ];

        if ($reversed) {
            $declarations = array_reverse($declarations);
        }

        return app(RegistryCompiler::class)->compile([
            ModuleManifest::make('example/notes')->title('Notes')->tablePrefix('nts')->extends($declarations),
        ], installedPackages: [], installedVersions: [
            'axiomasoft/corex' => new InstalledPackageVersion('axiomasoft/corex', '1.0.0', 'p13-fixture'),
            'example/notes' => new InstalledPackageVersion('example/notes', '1.0.0', 'p13-fixture'),
        ]);
    }
}
