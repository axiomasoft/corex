<?php

declare(strict_types=1);

namespace CoreX\SearchMeilisearch\Tests\Unit;

use CoreX\Config\Contracts\ConfigReportSection;
use CoreX\CoreServiceProvider;
use CoreX\SearchMeilisearch\Internal\MeilisearchConfigSection;
use CoreX\SearchMeilisearch\MeilisearchServiceProvider;
use Illuminate\Support\Facades\Artisan;
use Orchestra\Testbench\TestCase;

final class ConfigReportSectionTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [CoreServiceProvider::class, MeilisearchServiceProvider::class];
    }

    public function test_section_registers_in_report_order_and_redacts_its_secrets(): void
    {
        $sections = iterator_to_array($this->app->tagged(ConfigReportSection::class));
        $owned = array_values(array_filter($sections, static fn (ConfigReportSection $section): bool => $section instanceof MeilisearchConfigSection));
        self::assertCount(1, $owned);
        self::assertSame(60, $owned[0]->order());
        config(['corex-search-meilisearch.key' => 'a-private-section-secret']);
        self::assertSame(0, Artisan::call('corex:config:validate'));
        self::assertStringContainsString('secret corex-search-meilisearch.key=present', Artisan::output());
        self::assertStringNotContainsString('a-private-section-secret', Artisan::output());
        config(['corex-search-meilisearch.key' => null]);
        self::assertSame(0, Artisan::call('corex:config:validate'));
        self::assertStringContainsString('secret corex-search-meilisearch.key=absent', Artisan::output());
    }
}
