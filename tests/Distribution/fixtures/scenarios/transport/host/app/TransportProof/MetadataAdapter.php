<?php

declare(strict_types=1);

namespace App\TransportProof;

use CoreX\CustomFields\Schemas\Contracts\FieldMetadata;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\EntityHandle;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Intl\Locale\Contracts\LocaleResolver;
use CoreX\Intl\Locale\Data\LocaleInputs;
use CoreX\Intl\Locale\Data\LocalePolicy;
use CoreX\Operations\Metadata\Contracts\EntityMetadata;
use CoreX\Operations\Metadata\Data\MetadataView;
use CoreX\Search\Query\Contracts\SearchMetadata;
use Illuminate\Http\Request;

final readonly class MetadataAdapter implements EntityMetadata
{
    public function __construct(
        private FieldMetadata|SearchMetadata $owner,
        private LocaleResolver $locales,
        private LocalePolicy $policy,
    ) {}

    public function describe(DataScope $scope, EntityHandle $entity): MetadataView
    {
        $owner = $this->owner->describe(scope: $scope, entity: $entity);
        $body = JsonDocuments::map($owner->body);
        $request = resolve(Request::class);
        $language = str_replace('_', '-', $request->getPreferredLanguage(['en', 'ru-RU']) ?? 'en');
        $resolution = $this->locales->resolve(new LocaleInputs($language, null, 'equipment'), $this->policy);
        $body['capabilities']['locale'] = $resolution->used;

        return new MetadataView(
            body: JsonDocuments::capture($body),
            witness: hash('sha256', json_encode([$owner->witness, $resolution], JSON_THROW_ON_ERROR)),
        );
    }
}
