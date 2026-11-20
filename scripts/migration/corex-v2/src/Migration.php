<?php

declare(strict_types=1);

namespace CoreX\OfflineMigration;

use DateTimeImmutable;
use SQLite3;
use Throwable;

/**
 * @phpstan-import-type MappedRow from SourceSnapshot
 *
 * @phpstan-type InputPaths array{source_snapshot: string, target: string, profile: string}
 * @phpstan-type CutoverWindow array{starts_at: string, ends_at: string}
 * @phpstan-type PlanSegment array{id: 'subscriptions'|'deliveries', owner: string, source_table: string, target_table: string, requires: list<string>, rows: int, source_pk_digest: string, source_digest: string, semantic_digest: string}
 * @phpstan-type MigrationPlan array{
 *     format: int,
 *     inputs: InputPaths,
 *     descriptor_digests: InputPaths,
 *     source: array{
 *         artifact_sha: string,
 *         deployment_id: string,
 *         database_id: string,
 *         file: array{device: string, inode: string},
 *         snapshot_id: string,
 *         snapshot_sha256: string,
 *         schema_sha256: string,
 *         codec: string
 *     },
 *     target: array{
 *         artifact_sha: string,
 *         deployment_id: string,
 *         database_id: string,
 *         binding_generation: positive-int,
 *         recovery_generation: positive-int,
 *         file: array{device: string, inode: string},
 *         schema_sha256: string,
 *         codec: string
 *     },
 *     importer_sha256: string,
 *     profile: string,
 *     tenant_map: list<array{source_account: string, target_account: string, workspace: null}>,
 *     window: CutoverWindow,
 *     source_statuses: list<string>,
 *     segments: list<PlanSegment>,
 *     effect_keys: list<array{source: string, target: string, disposition: string}>,
 *     plan_id: string,
 *     plan_digest: string
 * }
 */
final readonly class Migration
{
    public const string Profile = 'stage0-webhooks';

    private SourceSnapshot $source;

    private TargetDatabase $target;

    /** @var array{subscriptions: list<MappedRow>, deliveries: list<MappedRow>} */
    private array $mapped;

    /** @var MigrationPlan */
    public array $plan;

    /**
     * @param  InputPaths  $paths
     * @param  array<string, mixed>  $trust
     */
    public function __construct(array $paths, array $trust)
    {
        $paths = [
            'source_snapshot' => fullPath($paths['source_snapshot']),
            'target' => fullPath($paths['target']),
            'profile' => fullPath($paths['profile']),
        ];
        $sourceInput = readDocument($paths['source_snapshot']);
        $source = authenticated($sourceInput, 'snapshot', $trust);
        $target = readDocument($paths['target']);
        $profile = readDocument($paths['profile']);
        exactFields($profile, ['id', 'account', 'source_epoch', 'window'], 'profile');
        check(field($profile, 'id') === self::Profile, 'Unknown migration profile.');
        $account = field($profile, 'account');
        $window = self::cutoverWindow($profile['window'] ?? null);

        $this->source = new SourceSnapshot($source);
        $this->target = new TargetDatabase($target);
        $sourceIdentity = [
            'artifact_sha' => shaField($source, 'artifact_sha', 40),
            'deployment_id' => field($source, 'deployment_id'),
            'database_id' => field($source, 'database_id'),
            'file' => $this->source->fileIdentity,
            'snapshot_id' => field($source, 'snapshot_id'),
            'snapshot_sha256' => shaField($source, 'snapshot_sha256'),
            'schema_sha256' => SourceSnapshot::Schema,
            'codec' => SourceSnapshot::Codec,
        ];
        $targetIdentity = $this->target->identity;
        check($sourceIdentity['file'] !== $this->target->fileIdentity
            && $sourceIdentity['database_id'] !== $targetIdentity['database_id']
            && $sourceIdentity['deployment_id'] !== $targetIdentity['deployment_id']
            && $sourceIdentity['artifact_sha'] !== $targetIdentity['artifact_sha'], 'Source and target identities must be distinct.');

        $this->mapped = $this->source->map($account, shaField($profile, 'source_epoch'));
        $segments = [];
        foreach ($this->mapped as $segment => $mapped) {
            $segments[] = [
                'id' => $segment,
                'owner' => 'axiomasoft/corex-webhooks',
                'source_table' => 'sys_api_webhook_'.$segment,
                'target_table' => TargetDatabase::Tables[$segment],
                'requires' => $segment === 'deliveries' ? ['subscriptions'] : [],
                'rows' => count($mapped),
                'source_pk_digest' => digestRows(array_column($mapped, 'source_pk')),
                'source_digest' => digestRows(array_column($mapped, 'source_digest')),
                'semantic_digest' => digestRows(array_column($mapped, 'target')),
            ];
        }
        $effects = array_map(
            fn (array $row): array => ['source' => $row['source_pk'], 'target' => $row['target']['effect_key'], 'disposition' => $row['target']['disposition']],
            $this->mapped['deliveries'],
        );

        $body = [
            'format' => 1,
            'inputs' => $paths,
            'descriptor_digests' => ['source_snapshot' => digest($sourceInput), 'target' => digest($target), 'profile' => digest($profile)],
            'source' => $sourceIdentity,
            'target' => [
                ...$targetIdentity,
                'file' => $this->target->fileIdentity,
                'schema_sha256' => TargetDatabase::Schema,
                'codec' => TargetDatabase::Codec,
            ],
            'importer_sha256' => self::importerDigest(),
            'profile' => self::Profile,
            'tenant_map' => [['source_account' => $account, 'target_account' => $account, 'workspace' => null]],
            'window' => $window,
            'source_statuses' => ['pending', 'succeeded', 'dead'],
            'segments' => $segments,
            'effect_keys' => $effects,
        ];
        $body['plan_id'] = digest($body);
        $this->plan = $body + ['plan_digest' => digest($body)];
    }

    /**
     * @param  array<string, mixed>  $plan
     * @param  array<string, mixed>  $trust
     */
    public static function fromPlan(array $plan, array $trust): self
    {
        $paths = objectFields($plan['inputs'] ?? null, 'Invalid plan inputs.');
        exactFields($paths, ['source_snapshot', 'target', 'profile'], 'plan inputs');
        $migration = new self([
            'source_snapshot' => field($paths, 'source_snapshot'),
            'target' => field($paths, 'target'),
            'profile' => field($paths, 'profile'),
        ], $trust);
        check(canonical($migration->plan) === canonical($plan), 'Plan inputs, fingerprints, or content changed.');

        return $migration;
    }

    /** @return MigrationPlan */
    public function dryRun(): array
    {
        $db = $this->target->connect(write: false);
        $db->exec('BEGIN');
        $this->target->assertIdentity($db);
        $this->target->assertEmpty($db);
        $this->source->assertSealed();

        return $this->plan;
    }

    /** @return array{status: string, plan_id: string, rows_written: int, segment: ?string} */
    public function copy(?string $selected): array
    {
        check($selected === null || isset($this->mapped[$selected]), 'Unknown copy segment.');
        $db = $this->target->connect(write: true);
        $planId = $this->plan['plan_id'];
        $written = 0;

        foreach ($this->mapped as $segment => $expected) {
            if ($selected !== null && $segment !== $selected) {
                continue;
            }
            do {
                $db->exec('BEGIN IMMEDIATE');

                try {
                    $this->source->assertSealed();
                    $this->target->assertIdentity($db);
                    $this->assertJournalSegments($db);
                    $subscriptions = $this->target->progress(
                        $db, $planId, 'subscriptions', $this->mapped['subscriptions'], requireComplete: $segment === 'deliveries',
                    );
                    $deliveries = $this->target->progress($db, $planId, 'deliveries', $this->mapped['deliveries']);
                    check($deliveries === 0 || $subscriptions === count($this->mapped['subscriptions']),
                        'Target deliveries precede a complete subscription segment.', 3);
                    $offset = $segment === 'deliveries' ? $deliveries : $subscriptions;

                    if ($offset === count($expected)) {
                        $db->exec('COMMIT');

                        break;
                    }
                    $this->target->insertNext($db, $planId, $segment, $expected, $offset);
                    self::testBarrier('before-commit', $segment);
                    $this->target->assertFile();
                    $this->source->assertSealed();
                    $db->exec('COMMIT');
                } catch (Throwable $failure) {
                    $db->exec('ROLLBACK');

                    throw $failure;
                }
                $written++;
                self::testBarrier('after-commit', $segment);
            } while ($offset + 1 < count($expected));
        }

        return ['status' => 'copied', 'plan_id' => $planId, 'rows_written' => $written, 'segment' => $selected];
    }

    /** @return array<'subscriptions'|'deliveries'|'status'|'plan_id'|'source_artifact'|'target_artifact'|'semantic_digest', int|string> */
    public function verify(): array
    {
        $db = $this->target->connect(write: false);
        $db->exec('BEGIN');
        $this->target->assertIdentity($db);
        $this->assertJournalSegments($db);
        $planId = $this->plan['plan_id'];
        $result = [];
        foreach ($this->mapped as $segment => $expected) {
            $result[$segment] = $this->target->progress($db, $planId, $segment, $expected, requireComplete: true);
        }
        check($db->querySingle('PRAGMA foreign_key_check') === null, 'Target contains orphan references.', 3);
        $this->source->assertSealed();

        return $result + [
            'status' => 'verified',
            'plan_id' => $planId,
            'source_artifact' => $this->plan['source']['artifact_sha'],
            'target_artifact' => $this->plan['target']['artifact_sha'],
            'semantic_digest' => digest(array_column($this->plan['segments'], 'semantic_digest')),
        ];
    }

    private function assertJournalSegments(SQLite3 $db): void
    {
        foreach (['migration_row_map', 'migration_checkpoint'] as $table) {
            check($db->querySingle("SELECT EXISTS(SELECT 1 FROM {$table} WHERE segment NOT IN ('subscriptions', 'deliveries'))") === 0,
                'Unknown segment in target ledger.', 3);
        }
    }

    /** @return CutoverWindow */
    private static function cutoverWindow(mixed $raw): array
    {
        $window = objectFields($raw, 'Invalid cutover window.');
        exactFields($window, ['starts_at', 'ends_at'], 'cutover window');
        $start = field($window, 'starts_at');
        $end = field($window, 'ends_at');
        foreach ([$start, $end] as $instant) {
            check(preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/D', $instant) === 1
                && (new DateTimeImmutable($instant))->format('Y-m-d\TH:i:s\Z') === $instant, 'Invalid UTC cutover instant.');
        }
        check($start < $end, 'Empty cutover window.');

        return ['starts_at' => $start, 'ends_at' => $end];
    }

    private static function importerDigest(): string
    {
        $files = glob(__DIR__.'/*.php');
        check($files !== false && $files !== [], 'Cannot find the importer files.');

        return digestRows(array_map(fileDigest(...), [dirname(__DIR__).'/bin/corex-migrate', ...$files]));
    }

    private static function testBarrier(string $point, string $segment): void
    {
        if (getenv('COREX_MIGRATION_TEST_BARRIER') === "{$point}:{$segment}") {
            fwrite(STDERR, "test barrier {$point}:{$segment}\n");
            fflush(STDERR);
            // The harness kills this child process. A bounded wait prevents an abandoned test hanging indefinitely.
            sleep(10);

            throw new MigrationError('Test harness did not terminate the interrupted copy.', 2);
        }
    }
}
