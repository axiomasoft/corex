<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Provisioning\Internal\Database;

use CoreX\Context\Data\AccountRef;
use CoreX\Tenancy\Account\Laravel\AccountTemplateSnapshot;
use CoreX\Tenancy\Laravel\Support\TenancyOptions;
use CoreX\Tenancy\Provisioning\Contracts\TemplateDatabaseCloner;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionResolverInterface;
use RuntimeException;
use Throwable;

/**
 * `CREATE DATABASE … TEMPLATE "tpl_v42" STRATEGY WAL_LOG` (golden-clone
 * provisioning) — `tpl_v42`
 * here is only an illustrative name; the real template name is read from
 * `root_templates.db_name`. `STRATEGY WAL_LOG` is mandatory — PG15+
 * defaults `CREATE DATABASE … TEMPLATE` to FILE_COPY, far slower for a
 * populated golden template.
 *
 * Template-parity gate: refuses to clone a template whose
 * `root_templates.status` isn't `ready` — the FROZEN DB_SCHEMA contract
 * carries no schema-hash column, so `status` is the only available parity
 * signal.
 *
 * Quarantine handling: PG rejects `CREATE DATABASE … TEMPLATE` while any
 * backend is connected to the template DB (SQLSTATE 55006 — "source
 * database is being accessed by other users"). The golden template is
 * never meant to have live connections; this is a defensive pre-step +
 * bounded retry, not a substitute for that invariant.
 *
 * @internal spec: D24, premortem A3/B3, R-11 §2.3 п.2, F-DDL1
 */
final class TemplateClonePostgreSQLManager implements TemplateDatabaseCloner
{
    private const int QUARANTINE_RETRIES = 5;

    private const int QUARANTINE_BACKOFF_MS = 200;

    public function __construct(
        private readonly TenancyOptions $options,
        private readonly ConnectionResolverInterface $connections,
        private readonly TemplateIntegrityVerifier $integrity,
    ) {}

    public function createForAccount(AccountRef $account): bool
    {
        $snapshot = AccountTemplateSnapshot::capture($account, $this->centralConnection());

        $this->integrity->assertCurrent(
            version: $snapshot->templateVersion,
            clusterId: $snapshot->clusterId,
        );

        $name = $snapshot->databaseName;
        $this->validateParameter($name);

        $template = $this->resolveReadyTemplate($snapshot->templateVersion);
        $this->validateParameter($template);

        $cluster = $this->resolveCluster($snapshot->clusterId);
        $this->validateParameter($cluster);

        $created = $this->createDatabase($name, $template);

        $this->connection()->statement("GRANT ALL PRIVILEGES ON DATABASE \"{$name}\" TO \"{$cluster}\"");
        $this->connection()->statement("ALTER DATABASE \"{$name}\" OWNER TO \"{$cluster}\"");

        return $created;
    }

    public function createDatabase(string $name, string $templateDbName): bool
    {
        $this->validateParameter($name);
        $this->validateParameter($templateDbName);
        for ($attempt = 1; $attempt <= self::QUARANTINE_RETRIES; $attempt++) {
            $this->terminateTemplateBackends($templateDbName);

            try {
                return $this->connection()->statement(
                    "CREATE DATABASE \"{$name}\" TEMPLATE \"{$templateDbName}\" STRATEGY WAL_LOG",
                );
            } catch (Throwable $e) {
                $isQuarantined = str_contains($e->getMessage(), '55006')
                    || str_contains($e->getMessage(), 'is being accessed by other users');

                if (! $isQuarantined || $attempt === self::QUARANTINE_RETRIES) {
                    throw $e;
                }

                usleep(self::QUARANTINE_BACKOFF_MS * 1000 * $attempt);
            }
        }

        throw new RuntimeException("Unreachable: template clone retries exhausted for [{$templateDbName}].");
    }

    private function terminateTemplateBackends(string $templateDbName): void
    {
        $this->connection()->statement(
            'SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = ? AND pid <> pg_backend_pid()',
            [$templateDbName],
        );
    }

    private function resolveReadyTemplate(int $version): string
    {
        $template = $this->connections->connection($this->centralConnection())
            ->table('root_templates')
            ->where('version', $version)
            ->first();

        if ($template === null || $template->status !== 'ready' || ! is_string($template->db_name)) {
            throw new RuntimeException(
                "Template version [{$version}] is not ready for cloning (template-parity gate, D24).",
            );
        }

        return $template->db_name;
    }

    private function resolveCluster(string $clusterId): string
    {
        $cluster = $this->connections->connection($this->centralConnection())
            ->table('root_clusters')
            ->where('id', $clusterId)
            ->first();

        if ($cluster === null || ! is_string($cluster->app_db_user)) {
            throw new RuntimeException("Cluster [{$clusterId}] not found in root_clusters.");
        }

        return $cluster->app_db_user;
    }

    public function deleteDatabase(string $name): bool
    {
        $this->validateParameter($name);

        return $this->connection()->statement('DROP DATABASE IF EXISTS "'.$name.'"');
    }

    public function databaseExists(string $name): bool
    {
        return $this->connection()->selectOne('SELECT datname FROM pg_database WHERE datname = ?', [$name]) !== null;
    }

    private function validateParameter(string $value): void
    {
        if (preg_match('/\A[a-zA-Z0-9_]+\z/D', $value) !== 1 || strlen($value) > 63) {
            throw new RuntimeException('Invalid PostgreSQL database or role identifier.');
        }
    }

    private function connection(): Connection
    {
        /** @var Connection */
        $connection = $this->connections->connection($this->centralConnection());

        return $connection;
    }

    private function centralConnection(): string
    {
        return $this->options->centralConnection;
    }
}
