<?php

declare(strict_types=1);

namespace CoreX\Tests\Feature;

use CoreX\Tests\Golden\GoldenTestCase;
use Illuminate\Support\Facades\Artisan;

final class ConfigReportCompositionTest extends GoldenTestCase
{
    public function test_package_sections_preserve_the_complete_report(): void
    {
        $this->declareTenancyHostConfiguration();
        config([
            'corex.pubsub.driver' => 'redis',
            'corex-tenancy.wakeup.driver' => 'root_registry',
            'corex-tenancy.oidc.enabled' => false,
            'corex-tenancy.oidc.client_secret' => null,
            'corex-intl.reference.cursor_secret' => '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef',
            'corex-search.cursor_secret' => null,
            'corex-search-meilisearch.key' => 'test-only-meilisearch-key',
        ]);

        self::assertSame(0, Artisan::call('corex:config:validate'));
        self::assertSame(<<<'REPORT'
driver pubsub.driver=redis
driver corex-tenancy.wakeup.driver=root_registry
driver corex-tenancy.oidc.enabled=false
adapter pubsub.redis=available
adapter wakeup.root_registry=available
adapter Tenant\Contracts\Runtime\TenantRuntime=available
adapter CoreX\Tenancy\Runtime\Contracts\AccountIsolationMapper=available
adapter CoreX\Tenancy\Provisioning\Contracts\AccountResources=available
adapter CoreX\Tenancy\Provisioning\Contracts\TemplateIntegrityChecks=available
adapter CoreX\Tenancy\Contracts\TemplateIntegrityVerifier=available
secret corex-tenancy.oidc.client_secret=absent
secret corex-intl.reference.cursor_secret=present
WARNING [secret-default] corex-intl.reference.cursor_secret
secret corex-search.cursor_secret=absent
secret corex-search-meilisearch.key=present

REPORT, Artisan::output());
    }
}
