<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Tests\Support;

use CoreX\Tenancy\Contracts\TemplateIntegrityVerifier;
use CoreX\Tenancy\Provisioning\Contracts\TemplateIntegrityChecks;
use CoreX\Tenancy\Provisioning\Data\ClaimRequest;
use CoreX\Tenancy\Provisioning\Data\QualifiedTemplate;
use CoreX\Tenancy\Provisioning\Data\TemplateAccountView;
use CoreX\Tenancy\Provisioning\Exceptions\TemplateIntegrityException;
use Illuminate\Support\Facades\DB;

/** Explicit host verdict fixture, not a second implementation of Tenant's physical verifier. */
final class TestTemplateIntegrity implements TemplateIntegrityChecks, TemplateIntegrityVerifier
{
    private array $trusted = [];

    public array $deniedDatabases = [];

    public function trust(string $id, string $database): void
    {
        $digest = hash('sha256', 'test-owner-receipt:'.$id.':'.$database);
        $this->trusted[$id] = $digest;
        DB::connection('pgsql')->table('root_templates')->where('id', $id)->update(['schema_hash' => $digest]);
    }

    public function revoke(string $id): void
    {
        unset($this->trusted[$id]);
    }

    public function verifyCurrent(int $version, ?string $clusterId = null): void
    {
        $template = DB::connection('pgsql')->table('root_templates')->where('version', $version)->first();

        if ($template === null || $template->status !== 'ready' || ! isset($this->trusted[$template->id]) || $this->trusted[$template->id] !== $template->schema_hash) {
            throw new TemplateIntegrityException('The fixture physical owner did not attest this template.');
        }

        if ($clusterId !== null) {
            $current = DB::connection('pgsql')->table('root_clusters')->where('id', $clusterId)->value('template_version');

            if ((int) $current !== $version) {
                throw new TemplateIntegrityException('The fixture cluster template changed.');
            }
        }
    }

    public function verifyAccount(TemplateAccountView $account): void
    {
        $this->verifyCurrent($account->templateVersion);

        if (in_array($account->databaseName, $this->deniedDatabases, true)) {
            throw new TemplateIntegrityException('The physical owner rejected the account resource.');
        }
    }

    public function qualify(ClaimRequest $request): QualifiedTemplate
    {
        $template = DB::connection('pgsql')->table('root_templates')->where('id', $request->templateId)->first();

        if ($template === null) {
            throw new TemplateIntegrityException('Unknown fixture template.');
        }
        $this->verifyCurrent((int) $template->version, $request->clusterId);

        return new QualifiedTemplate($request->clusterId, $request->templateId, (int) $template->version, $this->trusted[$template->id]);
    }
}
