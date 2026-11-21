<?php

declare(strict_types=1);

namespace App\Providers;

use App\FieldSearch\EquipmentAccess;
use App\FieldSearch\EquipmentLocaleSource;
use App\FieldSearch\EquipmentSource;
use App\FieldSearch\IntegerConverter;
use App\FieldSearch\LocalBroker;
use CoreX\CustomFields\Types\Contracts\FieldConverterProvider;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Contracts\FieldUsePolicy;
use CoreX\Messaging\Contracts\BrokerDeliverySource;
use CoreX\Messaging\Contracts\DurableMessagePublisher;
use CoreX\Search\Projection\Contracts\SearchProjectionContributor;
use CoreX\Search\Projection\Contracts\SearchSource;
use CoreX\SearchIntl\Contracts\LocalizedSearchValues;
use Illuminate\Support\ServiceProvider;

final class FieldSearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app['config']->set([
            'corex-search-intl.enabled' => true,
            'corex-intl.locale.supported_tags' => ['en', 'ru-RU', 'zh-Hant-TW'],
            'corex-intl.locale.app_tag' => 'en',
            'corex-intl.locale.explicit_fallback_tags' => ['en'],
        ]);
        $this->app->scoped(EquipmentAccess::class);
        $this->app->alias(EquipmentAccess::class, DataScopeAuthorizer::class);
        $this->app->alias(EquipmentAccess::class, FieldUsePolicy::class);
        $this->app->bind(SearchSource::class, EquipmentSource::class);
        $this->app->tag(EquipmentSource::class, SearchProjectionContributor::class);
        $this->app->bind(LocalizedSearchValues::class, EquipmentLocaleSource::class);
        $this->app->tag(IntegerConverter::class, FieldConverterProvider::class);
        $this->app->bind(BrokerDeliverySource::class, LocalBroker::class);
        $this->app->bind(DurableMessagePublisher::class, LocalBroker::class);
    }
}
