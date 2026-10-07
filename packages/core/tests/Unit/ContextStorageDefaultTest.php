<?php

declare(strict_types=1);

namespace CoreX\Tests\Unit;

use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\DatabaseConnectionConfig;
use CoreX\Context\Data\ImpersonationState;
use CoreX\Context\Data\ProvisionParams;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Data\WorkspaceRef;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ContextStorageDefaultTest extends TestCase
{
    public function test_default_tenant_profile_and_workspace_switch_preserve_account_and_boxed_state(): void
    {
        $account = new AccountRef(id: 'account', slug: 'account', status: 'active', features: [], limits: []);
        $workspace = new WorkspaceRef(id: 'workspace', slug: 'workspace', type: 'team', path: 'workspace', parentId: null);
        $tenant = new TenantContext(account: $account);
        self::assertFalse($tenant->isBoxed());
        self::assertNull($tenant->workspace);
        foreach ([false, true] as $boxed) {
            $switched = (new TenantContext(account: $account, boxed: $boxed))->forWorkspace(workspace: $workspace);
            self::assertSame($account, $switched->account);
            self::assertSame($workspace, $switched->workspace);
            self::assertSame($boxed, $switched->isBoxed());
        }
    }

    public function test_declarative_context_defaults_keep_the_existing_consumer_contract(): void
    {
        $config = new DatabaseConnectionConfig(driver: 'pgsql', host: '127.0.0.1', port: 5434, database: 'corex_test', username: 'corex');
        self::assertSame([], $config->options);
        self::assertSame('corex_test', $config->database);
        $params = new ProvisionParams(accountId: 'account', clusterId: 'cluster', templateVersion: 1, embeddingModel: 'model', embeddingDim: 128);
        self::assertNull($params->verticalCode);
        self::assertSame('ru', $params->locale);
        $expiry = new DateTimeImmutable('2030-01-01T00:00:00Z');
        $state = new ImpersonationState(grantId: 'grant', staffIdentityId: 'staff', targetUserId: 'target', expiresAt: $expiry);
        self::assertNull($state->reason);
        self::assertSame($expiry, $state->expiresAt);
    }
}
