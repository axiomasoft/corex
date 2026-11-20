<?php

declare(strict_types=1);

namespace CoreX\SearchMeilisearch\Tests\Unit;

use CoreX\CoreServiceProvider;
use CoreX\SearchMeilisearch\Internal\MeilisearchConfigSection;
use CoreX\SearchMeilisearch\SearchMeilisearchServiceProvider;
use Illuminate\Support\Facades\Artisan;
use Orchestra\Testbench\TestCase;
use Packman\Config\Contracts\ConfigReportSectionInterface as ConfigReportSection;

final class ConfigReportSectionTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [CoreServiceProvider::class, SearchMeilisearchServiceProvider::class];
    }

    public function test_section_registers_in_report_order_without_boot_credentials(): void
    {
        $sections = iterator_to_array($this->app->tagged(ConfigReportSection::class));
        $owned = array_values(array_filter($sections, static fn (ConfigReportSection $section): bool => $section instanceof MeilisearchConfigSection));
        self::assertCount(1, $owned);
        self::assertSame(60, $owned[0]->order());
        self::assertSame([], $owned[0]->secretKeys());
        self::assertSame(0, Artisan::call('packman:config:validate'));
        self::assertStringNotContainsString('corex-search-meilisearch.key', Artisan::output());
    }
}
