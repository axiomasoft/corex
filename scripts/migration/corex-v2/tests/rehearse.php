<?php

declare(strict_types=1);

namespace CoreX\OfflineMigration;

use RuntimeException;
use SQLite3;
use Throwable;

require __DIR__.'/../src/Input.php';
require __DIR__.'/../src/Database.php';
require __DIR__.'/../src/SourceSnapshot.php';
require __DIR__.'/../src/TargetDatabase.php';
require __DIR__.'/../src/Migration.php';

final class Rehearsal
{
    private int $assertions = 0;

    /** @var list<array{label: string, command: non-empty-list<string>, exit: int}> */
    private array $steps = [];

    /** @var array<string, non-empty-string> */
    private array $keys = [];

    public function __construct(
        private readonly string $output,
        private readonly string $artifact,
        private readonly ?string $ownerExport,
    ) {
        $this->expect(! file_exists($output) && mkdir($output, 0700), 'A fresh output directory is required.');
        $this->expect(preg_match('/^[a-f0-9]{40}$/D', $artifact) === 1 && $artifact !== SourceSnapshot::Artifact,
            'The fixture needs a distinct target Git SHA.');
        $trust = [];
        foreach (['backup' => 'snapshot', 'reviewer' => 'approval'] as $id => $purpose) {
            $this->keys[$id] = sodium_crypto_sign_keypair();
            $trust[$id] = [
                'principal' => "fixture-{$id}",
                'public_key' => base64_encode(sodium_crypto_sign_publickey($this->keys[$id])),
                'purposes' => [$purpose],
            ];
        }
        publish($output.'/trust.json', $trust);
        putenv('COREX_MIGRATION_TRUST_STORE='.$output.'/trust.json');
        putenv('COREX_MIGRATION_TEST_BARRIER');
    }

    /** @return array<string, mixed> */
    public function run(): array
    {
        $source = $this->snapshot('source');
        $clean = $this->prepare('uninterrupted', $source);
        $retry = $this->prepare('resumed', $source);
        $this->command('uninterrupted/copy', $this->copy($clean), 0);
        $cleanReceipt = $this->command('uninterrupted/verify', ['verify', '--plan', $clean.'/plan.json'], 0);
        $this->assertMaterialized($clean);

        $plan = readDocument($retry.'/plan.json');
        $approval = readDocument($retry.'/approval.json');
        $approvalBody = objectFields($approval['body'] ?? null, 'Invalid fixture approval body.');
        $missingSignature = $approval;
        unset($missingSignature['signature']);
        publish($retry.'/unsigned.json', $missingSignature);
        publish($retry.'/untrusted.json', $this->sign('approval', 'backup', $approvalBody));
        $wrongSignature = $approval;
        $wrongSignature['signature'] = base64_encode(str_repeat("\0", SODIUM_CRYPTO_SIGN_BYTES));
        publish($retry.'/wrong-signature.json', $wrongSignature);
        publish($retry.'/wrong-target.json', $this->sign('approval', 'reviewer', array_replace(
            $approvalBody, ['target_deployment' => 'another-deployment'],
        )));
        $this->refusesBeforeWrite($retry, 'unsigned', $this->copy($retry, approval: 'unsigned.json'), 2, 'approval envelope');
        $this->refusesBeforeWrite($retry, 'wrong-signer', $this->copy($retry, approval: 'untrusted.json'), 2, 'Untrusted approval signer');
        $this->refusesBeforeWrite($retry, 'wrong-signature', $this->copy($retry, approval: 'wrong-signature.json'), 2, 'Invalid approval signature');
        $this->refusesBeforeWrite($retry, 'wrong-approval-target', $this->copy($retry, approval: 'wrong-target.json'), 2, 'Approval is for a different');
        $this->refusesBeforeWrite($retry, 'wrong-digest', $this->copy($retry, digest: str_repeat('0', 64)), 2, 'approval digests differ');
        $this->refusesBeforeWrite($retry, 'segment-dependency', [...$this->copy($retry), '--segment', 'deliveries'], 3, 'Incomplete checkpoint');

        $descriptor = readDocument($retry.'/target.json');
        $this->overwrite($retry.'/target.json', array_replace($descriptor, ['recovery_generation' => 42]));
        $this->refusesBeforeWrite($retry, 'changed-descriptor', $this->copy($retry), 2, 'Plan inputs');
        $this->overwrite($retry.'/target.json', $descriptor);
        $this->targetSql($retry, 'UPDATE migration_identity SET recovery_generation = 42');
        $this->refusesBeforeWrite($retry, 'wrong-db-generation', $this->copy($retry), 2, 'Target database identity or generation changed');
        $this->targetSql($retry, 'UPDATE migration_identity SET recovery_generation = 1');
        rename($retry.'/target.sqlite', $retry.'/original.sqlite');
        copy($retry.'/original.sqlite', $retry.'/target.sqlite');
        $this->refusesBeforeWrite($retry, 'cloned-target', $this->copy($retry), 2, 'Plan inputs');
        unlink($retry.'/target.sqlite');
        rename($retry.'/original.sqlite', $retry.'/target.sqlite');

        $this->kill('resumed/crash-before-delivery-commit', $this->copy($retry), 'before-commit:deliveries');
        $this->expect($this->targetState($retry) === [2, 0, 2, 0], 'Interrupted pre-commit batch must leave no delivery, map, or checkpoint.');
        $this->command('resumed/partial-verify', ['verify', '--plan', $retry.'/plan.json'], 3, 'Incomplete checkpoint');

        $this->kill('resumed/crash-after-delivery-commit', $this->copy($retry), 'after-commit:deliveries');
        $this->expect($this->targetState($retry) === [2, 1, 3, 1], 'Post-commit crash must retain one delivery, map, and checkpoint together.');
        $resumed = $this->command('resumed/copy', [...$this->copy($retry), '--segment', 'deliveries'], 0);
        $this->expect($resumed['rows_written'] === 2, 'Resume must write only the two remaining deliveries.');
        $repeated = $this->command('resumed/copy-again', $this->copy($retry), 0);
        $this->expect($repeated['rows_written'] === 0, 'Replay of the approved plan must add no rows.');
        $retryReceipt = $this->command('resumed/verify', ['verify', '--plan', $retry.'/plan.json'], 0);
        $this->expect(shaField($cleanReceipt, 'semantic_digest') === shaField($retryReceipt, 'semantic_digest'), 'Uninterrupted and resumed copies must agree.');
        $this->expect(field($retryReceipt, 'plan_id') === field($plan, 'plan_id'), 'Resume must keep the approved plan ID.');
        $this->assertMaterialized($retry);

        $this->targetSql($clean, "UPDATE webhook_deliveries SET delivery_json = json_replace(delivery_json, '$.delivery.event_id', 'changed-with-same-pk') WHERE disposition = 'pending'");
        $this->command('uninterrupted/corrupt-verify', ['verify', '--plan', $clean.'/plan.json'], 3, 'Target semantic digest mismatch');
        $this->targetSql($clean, "UPDATE webhook_deliveries SET delivery_json = json_replace(delivery_json, '$.delivery.event_id', 'event-pending') WHERE disposition = 'pending'");
        $this->command('uninterrupted/restored-verify', ['verify', '--plan', $clean.'/plan.json'], 0);

        $unknown = $this->snapshot('unknown-source', <<<'SQL'
            UPDATE sys_api_webhook_deliveries
            SET status = 'dead', attempt = 1, last_error = 'retry_exhausted', finished_at = '2026-10-01 12:00:01'
            WHERE event_id = 'event-pending';
            INSERT INTO sys_api_webhook_attempts
            SELECT 'unresolved-attempt', id, 1, 'fixture-worker', '2026-10-01 12:00:00', '2026-10-01 12:00:01',
                'unknown', NULL, NULL, 'ack_unknown'
            FROM sys_api_webhook_deliveries WHERE event_id = 'event-pending';
            SQL);
        $unknownRun = $this->prepare('unknown', $unknown, expected: 3);
        $blocked = readDocument($unknownRun.'/plan.json.blocked.json');
        $this->expect(field($blocked, 'status') === 'blocked' && str_contains(field($blocked, 'reason'), 'unsupported webhook effect')
            && ! is_file($unknownRun.'/plan.json') && $this->targetState($unknownRun) === [0, 0, 0, 0],
            'Unknown effect must emit only diagnostics and leave the target empty.');

        $changedSource = $this->snapshot('changed-source');
        $changed = $this->prepare('changed', $changedSource);
        $changedDatabase = $this->sourceDatabase($changedSource);
        chmod($changedDatabase, 0600);
        $db = database($changedDatabase, write: true);
        $db->exec("UPDATE sys_api_webhook_subscriptions SET endpoint_url = 'https://changed.example.invalid' WHERE id = 'subscription-a'");
        $db->close();
        chmod($changedDatabase, 0400);
        $this->refusesBeforeWrite($changed, 'snapshot-drift', $this->copy($changed), 2, 'Source snapshot identity or digest changed');

        $plannedSource = objectFields($plan['source'] ?? null, 'Invalid fixture plan source.');
        $this->expect(fileDigest($this->sourceDatabase($source)) === shaField($plannedSource, 'snapshot_sha256'),
            'Successful and interrupted operations must leave the source bytes unchanged.');
        $sqliteVersion = SQLite3::version()['versionString'] ?? null;
        check(is_string($sqliteVersion), 'The SQLite backend did not report a version.');
        $proof = $retryReceipt + [
            'backend' => 'SQLite '.$sqliteVersion,
            'php_version' => PHP_VERSION,
            'source_input' => $this->ownerExport === null ? 'frozen-sql' : 'direct-owner-sqlite',
            'assertions' => $this->assertions,
            'commands' => $this->steps,
        ];
        publish($this->output.'/summary.json', $proof);

        return $proof;
    }

    private function assertMaterialized(string $run): void
    {
        $this->expect($this->targetState($run) === [2, 3, 5, 3], 'The verified target must have the exact row, map, and checkpoint counts.');
        $db = database($run.'/target.sqlite', write: false);
        $rows = iterator_to_array(rows($db, <<<'SQL'
            SELECT effect_key, subscription_id, disposition, json_extract(delivery_json, '$.delivery.event_id') AS event_id,
                json_extract(delivery_json, '$.delivery.scope.account') AS account,
                json_extract(delivery_json, '$.delivery.scope.actor') AS actor,
                json_extract(delivery_json, '$.delivery.created_at') AS created_at,
                json_extract(delivery_json, '$.delivery.body') AS body, json_array_length(delivery_json, '$.attempts') AS attempts
            FROM webhook_deliveries ORDER BY event_id
            SQL));
        $expected = [
            ['subscription-a', 'history', 'event-dead', 'user-a', '12:00:01', ['error' => 'payload_too_large'], 0],
            ['subscription-a', 'pending', 'event-pending', 'user-a', '12:00:01', ['value' => null], 0],
            ['subscription-b', 'history', 'event-succeeded', 'user-b', '12:00:00', ['value' => []], 1],
        ];
        $this->expect(count($rows) === count($expected), 'The owner table must contain each delivery exactly once.');
        foreach ($expected as $index => [$subscription, $state, $event, $actor, $time, $value, $attempts]) {
            $body = json_encode([
                'specversion' => '1.0',
                'id' => $event,
                'source' => 'equipment/registry',
                'type' => 'equipment.changed.v1',
                'time' => "2026-10-01T{$time}.000000Z",
                'datacontenttype' => 'application/json',
                'data' => $value,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            $this->expect($rows[$index] === [
                'effect_key' => substr(hash('sha256', "{$subscription}|{$event}"), 0, 32),
                'subscription_id' => $subscription,
                'disposition' => $state,
                'event_id' => $event,
                'account' => 'account-a',
                'actor' => $actor,
                'created_at' => "2026-10-01T{$time}Z",
                'body' => $body,
                'attempts' => $attempts,
            ], "Unexpected target business or effect state for [{$event}].");
        }
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{body: array<string, mixed>, signature: string}
     */
    private function sign(string $purpose, string $id, array $body): array
    {
        $body = ['key_id' => $id, 'principal' => "fixture-{$id}"] + $body;
        $signature = sodium_crypto_sign_detached("corex-migrate/stage0/{$purpose}\n".canonical($body), sodium_crypto_sign_secretkey($this->keys[$id]));

        return ['body' => $body, 'signature' => base64_encode($signature)];
    }

    private function snapshot(string $name, ?string $variation = null): string
    {
        $path = $this->output.'/'.$name.'.sqlite';

        if ($name === 'source' && $this->ownerExport !== null) {
            $this->expect(copy($this->ownerExport, $path) && fileDigest($path) === fileDigest($this->ownerExport),
                'Cannot copy the owner export byte for byte.');
        } else {
            $db = new SQLite3($path);
            $db->enableExceptions(true);
            $db->exec(fileContents(__DIR__.'/fixtures/source-v1-schema.sql'));
            $db->exec(fileContents(__DIR__.'/fixtures/source-v1-data.sql'));

            if ($variation !== null) {
                $db->exec($variation);
            }
            $db->close();
        }
        chmod($path, 0400);
        $descriptor = $this->output.'/'.$name.'.json';
        publish($descriptor, $this->sign('snapshot', 'backup', [
            'artifact_sha' => SourceSnapshot::Artifact,
            'deployment_id' => 'test-v1',
            'database_id' => 'test-v1-db',
            'snapshot_id' => 'snapshot-'.$name,
            'snapshot_sha256' => fileDigest($path),
            'schema_sha256' => SourceSnapshot::Schema,
            'codec' => SourceSnapshot::Codec,
            'database' => $path,
        ]));

        return $descriptor;
    }

    private function sourceDatabase(string $descriptor): string
    {
        $document = readDocument($descriptor);

        return field(objectFields($document['body'] ?? null, 'Invalid fixture snapshot body.'), 'database');
    }

    private function prepare(string $name, string $source, int $expected = 0): string
    {
        $run = $this->output.'/'.$name;
        $this->expect(mkdir($run, 0700), "Cannot create [{$name}].");
        $path = $run.'/target.sqlite';
        $db = new SQLite3($path);
        $db->enableExceptions(true);
        $db->exec(fileContents(__DIR__.'/fixtures/target-stage0-schema.sql'));
        $identity = [
            'database_id' => 'target-'.$name,
            'deployment_id' => 'test-v2-'.$name,
            'artifact_sha' => $this->artifact,
            'binding_generation' => 1,
            'recovery_generation' => 1,
        ];
        execute($db, 'INSERT INTO migration_identity VALUES (?, ?, ?, ?, ?)', array_values($identity));
        $db->close();
        publish($run.'/target.json', $identity + [
            'database' => $path,
            'schema_sha256' => TargetDatabase::Schema,
            'codec' => TargetDatabase::Codec,
        ]);
        publish($run.'/profile.json', [
            'id' => Migration::Profile,
            'account' => 'account-a',
            'source_epoch' => str_repeat('a', 64),
            'window' => ['starts_at' => '2026-10-01T12:01:00Z', 'ends_at' => '2026-10-01T12:15:00Z'],
        ]);

        $before = fileDigest($path);
        $this->command($name.'/plan', ['plan', '--source-snapshot', $source, '--target', $run.'/target.json',
            '--profile', $run.'/profile.json', '--output', $run.'/plan.json'], $expected,
            $expected === 3 ? 'unsupported webhook effect' : null);
        $this->expect(fileDigest($path) === $before, 'Dry-run must leave the target byte-for-byte unchanged.');

        if ($expected === 0) {
            $plan = readDocument($run.'/plan.json');
            $sourceDescriptor = objectFields($plan['source'] ?? null, 'Invalid fixture plan source.');
            $targetDescriptor = objectFields($plan['target'] ?? null, 'Invalid fixture plan target.');
            publish($run.'/approval.json', $this->sign('approval', 'reviewer', [
                'plan_digest' => shaField($plan, 'plan_digest'),
                'snapshot_sha256' => shaField($sourceDescriptor, 'snapshot_sha256'),
                'target_deployment' => field($targetDescriptor, 'deployment_id'),
                'profile' => field($plan, 'profile'),
            ]));
        }

        return $run;
    }

    /** @return non-empty-list<string> */
    private function copy(string $run, string $approval = 'approval.json', ?string $digest = null): array
    {
        return ['copy', '--plan', $run.'/plan.json', '--approval', $run.'/'.$approval,
            '--approve-digest', $digest ?? shaField(readDocument($run.'/plan.json'), 'plan_digest')];
    }

    /** @return array{int, int, int, int} */
    private function targetState(string $run): array
    {
        $db = database($run.'/target.sqlite', write: false);

        $subscriptions = $db->querySingle('SELECT COUNT(*) FROM webhook_subscriptions');
        $deliveries = $db->querySingle('SELECT COUNT(*) FROM webhook_deliveries');
        $mapping = $db->querySingle('SELECT COUNT(*) FROM migration_row_map');
        $checkpoint = $db->querySingle("SELECT COALESCE(MAX(rows), 0) FROM migration_checkpoint WHERE segment = 'deliveries'");
        $db->close();
        check(is_int($subscriptions) && is_int($deliveries) && is_int($mapping) && is_int($checkpoint),
            'The target fixture did not report integer counts.');

        return [$subscriptions, $deliveries, $mapping, $checkpoint];
    }

    /** @param array<string, mixed> $document */
    private function overwrite(string $path, array $document): void
    {
        $bytes = canonical($document).PHP_EOL;
        $this->expect(file_put_contents($path, $bytes) !== false && fileContents($path) === $bytes, 'Cannot write test variation.');
    }

    private function targetSql(string $run, string $sql): void
    {
        $db = database($run.'/target.sqlite', write: true);
        $db->exec($sql);
        $db->close();
    }

    /** @param non-empty-list<string> $arguments */
    private function refusesBeforeWrite(string $run, string $name, array $arguments, int $exit, string $reason): void
    {
        $before = fileDigest($run.'/target.sqlite');
        $this->command(basename($run).'/'.$name, $arguments, $exit, $reason);
        $this->expect(fileDigest($run.'/target.sqlite') === $before, "[{$name}] changed database bytes.");
    }

    /**
     * @param  non-empty-list<string>  $arguments
     * @return array<string, mixed>
     */
    private function command(string $label, array $arguments, int $exit, ?string $reason = null): array
    {
        $result = $this->process($label, $arguments);
        $this->expect($result['exit'] === $exit, $label.' exited unexpectedly. '.trim($result['stderr']));

        if ($reason !== null) {
            $this->expect(str_contains($result['stderr'], $reason), $label.' returned an unexpected diagnostic. '.trim($result['stderr']));
        }

        if ($exit === 0) {
            $payload = objectFields(
                json_decode($result['stdout'], associative: true, flags: JSON_THROW_ON_ERROR),
                "Expected a JSON object from [{$label}].",
            );
            // Successful plan status is separate from the signed plan document.
            $receipt = $arguments[0] === 'plan' ? $label.'-status' : $label;
            publish($this->output.'/'.$receipt.'.json', $payload);

            return $payload;
        }

        return [];
    }

    /** @param non-empty-list<string> $arguments */
    private function kill(string $label, array $arguments, string $barrier): void
    {
        putenv('COREX_MIGRATION_TEST_BARRIER='.$barrier);

        try {
            $result = $this->process($label, $arguments, $barrier);
        } finally {
            putenv('COREX_MIGRATION_TEST_BARRIER');
        }
        $this->expect($result['exit'] === 137 && str_contains($result['stderr'], 'test barrier '.$barrier),
            $label.' failed to interrupt the process at its transaction boundary (exit '.$result['exit'].'). '.trim($result['stderr']));
    }

    /**
     * @param  non-empty-list<string>  $arguments
     * @return array{exit: int, stderr: string, stdout: string}
     */
    private function process(string $label, array $arguments, ?string $barrier = null): array
    {
        $command = [PHP_BINARY, dirname(__DIR__).'/bin/corex-migrate', ...$arguments];
        $process = proc_open($command, [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $this->expect(is_resource($process), 'Could not start migration CLI.');
        $stderr = '';

        if ($barrier !== null) {
            stream_set_timeout($pipes[2], 5);
            $stderr = (string) fgets($pipes[2]);
            proc_terminate($process, SIGKILL);
        }
        $stdout = stream_get_contents($pipes[1]);
        $remainingStderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_get_status($process);
        // Pipe closure may be observed before waitpid reports the completed signal delivery.
        while ($barrier !== null && $status['running']) {
            usleep(1000);
            $status = proc_get_status($process);
        }
        $exit = proc_close($process);

        if ($status['signaled']) {
            $exit = 128 + $status['termsig'];
        }
        check($stdout !== false && $remainingStderr !== false, "Cannot read output from [{$label}].");
        $stderr .= $remainingStderr;
        $this->steps[] = ['label' => $label, 'command' => $command, 'exit' => $exit];
        file_put_contents($this->output.'/'.$label.'.stderr', $stderr);
        file_put_contents($this->output.'/'.$label.'.stdout', $stdout);

        return ['exit' => $exit, 'stderr' => $stderr, 'stdout' => $stdout];
    }

    /** @phpstan-assert true $condition */
    private function expect(bool $condition, string $message): void
    {
        if (! $condition) {
            throw new RuntimeException($message);
        }
        $this->assertions++;
    }
}

try {
    check(isset($argv[1]) && count($argv) <= 4, 'Usage: php rehearse.php FRESH_OUTPUT_DIR [TARGET_GIT_SHA [OWNER_SQLITE_EXPORT]]');
    $parent = realpath(dirname($argv[1]));
    check($parent !== false, 'Output parent directory must exist.');
    $output = $parent.'/'.basename($argv[1]);
    $proof = new Rehearsal($output, $argv[2] ?? str_repeat('2', 40), isset($argv[3]) ? fullPath($argv[3]) : null);
    fwrite(STDOUT, canonical($proof->run()).PHP_EOL);
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage().PHP_EOL);
    exit(1);
}
