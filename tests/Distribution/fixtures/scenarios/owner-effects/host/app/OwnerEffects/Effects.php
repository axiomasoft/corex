<?php

declare(strict_types=1);

namespace App\OwnerEffects;

use App\Scenario\FixtureLifecycleOperators;
use App\Scenario\Scenario;
use App\Scenario\ScenarioQueue;
use App\Scenario\ScenarioSetup;
use Closure;
use CoreX\Access\Roles\DirectGrants;
use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Data\Contracts\DataScopeCodec;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\JsonDocument;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\ImportExport\Contracts\ExportPublications;
use CoreX\ImportExport\Contracts\ExportSchemaWitness;
use CoreX\ImportExport\Contracts\ExportSource;
use CoreX\ImportExport\Contracts\ImportExportFactory;
use CoreX\ImportExport\Contracts\TransferResultReader;
use CoreX\ImportExport\Data\CanonicalRecord;
use CoreX\ImportExport\Data\Export\ExportCursor;
use CoreX\ImportExport\Data\Export\ExportManifest;
use CoreX\ImportExport\Data\Export\ExportPage;
use CoreX\ImportExport\Data\ExportSession;
use CoreX\ImportExport\Data\TransferLimits;
use CoreX\ImportExport\Data\TransferRef;
use CoreX\Invocation\Data\InvocationMetadata;
use CoreX\Media\Data\MediaContext;
use CoreX\Media\Data\UploadFile;
use CoreX\Media\Data\UploadInput;
use CoreX\Media\Files\Contracts\MediaLibrary;
use CoreX\Operations\Bulk\Contracts\BulkOperationStore;
use CoreX\Operations\Bulk\Data\BulkOperationDefinition;
use CoreX\Operations\Definition\Data\Operation;
use CoreX\Operations\Definition\Enums\OperationMode;
use CoreX\Operations\Idempotency\Enums\IdempotencyRequirement;
use CoreX\Operations\Invocation\Data\OperationInvocation;
use CoreX\Operations\Invocation\Laravel\ResolvedContexts;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Packman\Modules\Contracts\Lifecycle\ModuleLifecycle;
use Packman\Schema\Contracts\Migration\SchemaMigrator;
use RuntimeException;

final class Effects implements ExportSchemaWitness, ExportSource
{
    public const string MEDIA = 'axiomasoft/corex-media';

    public const string ENTITY = 'cas.equipment';

    public const string MODULE = 'thirdparty/custom-attribute-source';

    public const string TRANSFER = '51000000-0000-7000-8000-000000000001';

    public const string MANIFEST = '51000000-0000-7000-8000-000000000002';

    public const string BULK = '51000000-0000-7000-8000-000000000003';

    public const string LOCAL_TRANSFER = '51000000-0000-7000-8000-000000000004';

    public const string LOCAL_MANIFEST = '51000000-0000-7000-8000-000000000005';

    public static function enter(string $account, ?string $workspace, Closure $work): mixed
    {
        return ScenarioQueue::inScope($account, $workspace, $account === Scenario::ACCOUNT_A ? Scenario::USER_A : Scenario::USER_B,
            static fn () => $work(self::scope(), self::media()));
    }

    public static function scope(): DataScope
    {
        return resolve(DataScopeFactory::class)->forCurrentPrincipal(resolve(TenantContextResolver::class)->current());
    }

    public static function media(): MediaContext
    {
        $scope = self::scope();

        return new MediaContext(resolve(TenantContextResolver::class)->current(), $scope->principalKind, $scope->principalId);
    }

    public static function identity(DataScope $scope): string
    {
        return hash('sha256', json_encode($scope, JSON_THROW_ON_ERROR));
    }

    public static function setup(): array
    {
        $budget = DB::connection('owner_budget');
        foreach (['a', 'b', 'control', 'budget'] as $role) {
            $db = $role === 'budget' ? $budget : Scenario::connection($role);
            $name = getenv('COREX_SCENARIO_DB_'.strtoupper($role));
            $identity = $db->selectOne("select current_database() as db, current_setting('server_version_num') as version");

            if (! preg_match('/^corex_scenario_[0-9a-f]{16}_'.$role.'_test$/D', $name) || $identity->db !== $name
                || ! in_array($identity->version, ['160014', '180006'], true) || $identity->version !== getenv('COREX_SCENARIO_PG_VERSION')
                || ! in_array(PHP_VERSION, ['8.4.27', '8.5.12'], true)) {
                throw new RuntimeException('Unexpected owner-effects PHP or PostgreSQL identity.');
            }
        }
        $sets = ScenarioSetup::install('owner-effects', true);
        $sets['budget'] = resolve(SchemaMigrator::class)->migrateSets(['axiomasoft/corex-operations:budget']);
        $a = Scenario::connection('a');
        $w2 = Scenario::workspace(Scenario::W2);
        $a->table('wsp_workspaces')->insert(['id' => $w2->id, 'slug' => $w2->slug, 'name' => $w2->slug, 'type' => $w2->type, 'path' => $w2->path]);
        $a->table('wsp_workspace_members')->insert(['id' => (string) Str::uuid7(), 'workspace_id' => $w2->id, 'user_id' => Scenario::USER_A, 'status' => 'active']);
        MediaInventory::install();
        foreach ([Scenario::ACCOUNT_A => 'a', Scenario::ACCOUNT_B => 'b'] as $account => $role) {
            self::enter($account, null, static function (DataScope $scope) use ($role): void {
                $paths = array_values(array_filter(resolve('migrator')->paths(), static fn ($path) => str_starts_with(realpath($path) ?: '', base_path('vendor/axiomasoft/vaulter-'))));

                if ($paths === [] || Artisan::call('migrate', ['--path' => $paths, '--realpath' => true, '--database' => 'scenario_'.$role, '--force' => true]) !== 0) {
                    throw new RuntimeException('Vaulter owner migrations failed. '.Artisan::output());
                }
                foreach ([self::MEDIA, self::MODULE] as $module) {
                    FixtureLifecycleOperators::grant($scope, $module, 'enable');
                    FixtureLifecycleOperators::grant($scope, $module, 'inspect');
                    resolve(ModuleLifecycle::class)->enable($module);
                }
                DB::unprepared(<<<'SQL'
                    CREATE TABLE scenario_vaulter_journal(id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY, owner text NOT NULL, reference text NOT NULL, blob text NOT NULL, action text NOT NULL, scope jsonb NOT NULL);
                    CREATE TABLE scenario_file_grants(scope text, file text, ability text, PRIMARY KEY(scope,file,ability));
                    CREATE TABLE scenario_operation_grants(scope text PRIMARY KEY);
                    CREATE TABLE scenario_charged_effects(effect text PRIMARY KEY,scope text NOT NULL);
                    SQL);
                DB::table('cas_equipment')->insert(['id' => Scenario::RECORD, 'name' => 'Inspection photo']);
            });
        }
        foreach ([[Scenario::ACCOUNT_A, Scenario::W1], [Scenario::ACCOUNT_A, Scenario::W2], [Scenario::ACCOUNT_B, Scenario::W2]] as [$account, $workspace]) {
            self::enter($account, $workspace, static function (DataScope $scope): void {
                resolve(DirectGrants::class)->grant(auth()->user(), self::ENTITY, 'read');
                MediaInventory::recordGrant($scope);
                foreach ([[self::ENTITY, Scenario::RECORD, 'read', 'record'], [self::ENTITY, Scenario::RECORD, 'write', 'record'],
                    [self::ENTITY, Scenario::RECORD, 'export', 'name'], ['import-export.transfer', self::TRANSFER, 'read', 'record'],
                    ['import-export.transfer', self::TRANSFER, 'write', 'record'], ['import-export.transfer', self::TRANSFER, 'export', 'record'],
                    ['import-export.manifest', self::MANIFEST, 'export', 'record'], ['import-export.transfer', self::LOCAL_TRANSFER, 'write', 'record']] as [$entity, $id, $use, $field]) {
                    DB::table('scenario_field_grants')->insertOrIgnore(['principal_id' => $scope->principalId, 'workspace_id' => $scope->workspaceId,
                        'record_id' => $id, 'entity' => $entity, 'field_id' => $field, 'field_use' => $use]);
                }
                DB::table('scenario_operation_grants')->insert(['scope' => self::identity($scope)]);
                DB::table('scenario_file_grants')->insert(['scope' => self::identity($scope), 'file' => 'attachment/image/png', 'ability' => 'store']);
            });
        }

        return $sets;
    }

    public static function upload(): string
    {
        $scope = self::scope();
        $image = imagecreatetruecolor(10, 5);
        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        $file = resolve(MediaLibrary::class)->store(self::media(), new UploadInput('owner-effects-source', new UploadFile($bytes, 'source.png', true, strlen($bytes), 'image/png'), 'attachment'));
        foreach (['read', 'attach', 'detach', 'delete'] as $ability) {
            DB::table('scenario_file_grants')->insertOrIgnore(['scope' => self::identity($scope), 'file' => $file->id, 'ability' => $ability]);
        }

        return $file->id;
    }

    public static function invocation(): OperationInvocation
    {
        $op = new Operation(self::MEDIA, 'fixture.media.charge', 'v2', 'POST', '/fixture/media/charge', 'media.charge',
            Charge::class, JsonDocuments::capture([]), JsonDocuments::capture([]), [], 200, OperationMode::Command,
            idempotency: IdempotencyRequirement::Required, contractHash: hash('sha256', 'fixture.media.charge/1'),
            effectMode: 'tenant_transactional', cost: ['base' => 5, 'side_effect' => 0]);
        $context = ResolvedContexts::make(resolve(TenantContextResolver::class)->current(), auth()->user(), new InvocationMetadata('owner-effects', 'v2', $op->id, 'POST'));

        return new OperationInvocation($op, $context, JsonDocuments::capture([]), [], 'owner-effects-charge');
    }

    public function page(DataScope $scope, ExportManifest $manifest, ExportCursor $after, TransferLimits $limits): ExportPage
    {
        $row = DB::table('cas_equipment')->where('id', Scenario::RECORD)->sole();

        return new ExportPage([new CanonicalRecord(self::ENTITY, 'fixture', $row->id, '1', null, ['name' => $row->name],
            hash('sha256', $row->name))], null, true);
    }

    private static function projectionHash(): string
    {
        $fields = DB::select("SELECT attname, format_type(atttypid, atttypmod) AS type FROM pg_attribute
            WHERE attrelid = 'public.cas_equipment'::regclass AND attname IN ('id','name') AND NOT attisdropped ORDER BY attname");

        if (array_column($fields, 'attname') !== ['id', 'name']) {
            throw new RuntimeException('Published export projection is unavailable.');
        }

        return hash('sha256', json_encode([self::MODULE, self::ENTITY, $fields], JSON_THROW_ON_ERROR));
    }

    public function matches(DataScope $scope, string $expectedHash, JsonDocument $roster): bool
    {
        return $scope == self::scope() && JsonDocuments::map($roster) === ['owners' => [self::MODULE]]
            && hash_equals(self::projectionHash(), $expectedHash);
    }

    public static function knownReferences(DataScope $scope): array
    {
        $stamp = ['owners' => [self::MODULE]];
        $operation = resolve(BulkOperationStore::class)->submit(new BulkOperationDefinition(self::BULK, self::BULK, 'fixture.media.export', 'v2',
            hash('sha256', 'fixture.media.export/1'), resolve(DataScopeCodec::class)->encode($scope), $scope->principalId,
            'fixture', JsonDocuments::capture($stamp), JsonDocuments::capture(['kind' => 'export', 'record' => Scenario::RECORD])));
        $ref = new TransferRef(self::TRANSFER);
        $session = self::exportSession($scope, $ref, self::MANIFEST);
        $sealed = $session->runner->seal($scope, $ref->id, $session->manifest, $session->cursor);
        resolve(ExportPublications::class)->publish($scope, $ref, $sealed);
        $bytes = $session->runner->emit($scope, $sealed, new ExportCursor('0', '0', $sealed->partsHash));

        return ['operation' => $operation->definition->id, 'transfer' => $ref->id, 'sealed' => $sealed, 'sha256' => hash('sha256', $bytes),
            'result' => resolve(TransferResultReader::class)->result($scope, $ref)];
    }

    public static function exportSession(DataScope $scope, TransferRef $transfer, string $manifest): ExportSession
    {
        $draft = new ExportManifest($manifest, 'coherent', self::projectionHash(), $scope->restoreEpoch,
            JsonDocuments::capture(['owners' => [self::MODULE]]), str_repeat('0', 64));

        return resolve(ImportExportFactory::class)->openExport($scope, $transfer, new self, $draft, new ExportCursor('0', '0', $draft->partsHash));
    }
}
