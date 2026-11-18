<?php

declare(strict_types=1);

namespace Flex\Crm\ApiShared;

use Packman\Attributes\Internal;

/** JSON Schema for the existing bounded operation payloads. */
#[Internal]
final class CrmSchemas
{
    /** @return array<string, mixed> */
    public static function record(bool $company, bool $request = false): array
    {
        $properties = [];
        foreach ($company ? ['name' => 255, 'legal_name' => 255, 'domain' => 190, 'inn' => 12, 'kpp' => 9, 'ogrn' => 15] : ['name_first' => 120, 'name_last' => 120, 'name_middle' => 120, 'display_name' => 255, 'job_title' => 190] as $key => $length) {
            if ($request && $key === 'display_name') {
                continue;
            }
            $properties[$key] = ['type' => in_array($key, ['name', 'display_name'], true) ? 'string' : ['string', 'null'], 'maxLength' => $length];
        }
        foreach (['phones', 'emails'] as $key) {
            $channel = ['value' => ['type' => 'string'], 'type' => ['type' => ['string', 'null'], 'maxLength' => $request ? 32 : 64], 'is_primary' => ['type' => 'boolean'], 'norm' => ['type' => 'string']];
            $properties[$key] = ['type' => 'array', 'items' => self::object($channel, $request ? ['value'] : array_keys($channel))];
        }

        if ($company) {
            $properties['address'] = ['type' => ['object', 'array', 'null']];
            $properties['bank_details'] = ['type' => ['object', 'array', 'null']];
        } else {
            $properties['birthday'] = ['type' => ['string', 'null'], 'format' => 'date'];
        }
        foreach (['source_id', 'owner_id'] as $key) {
            $properties[$key] = ['type' => $request ? ['string', 'integer', 'null'] : ['string', 'null']];
        }

        if ($request) {
            $properties['account_level'] = ['type' => 'boolean'];
        } else {
            $properties['id'] = ['type' => 'string'];
            $properties['workspace_id'] = ['type' => ['string', 'null']];
        }

        return self::object($properties, $request ? ($company ? ['name'] : []) : array_keys($properties));
    }

    /** @return array<string, mixed> */
    public static function listing(bool $company): array
    {
        return self::object(['items' => ['type' => 'array', 'items' => self::record($company)]], ['items']);
    }

    /** @return array<string, mixed> */
    public static function listInput(): array
    {
        return self::object(['limit' => ['anyOf' => [['type' => 'integer', 'minimum' => 1, 'maximum' => 100], ['type' => 'string', 'pattern' => '^([1-9]|[1-9][0-9]|100)$']]]], []);
    }

    /** @return array<string, mixed> */
    public static function association(bool $request = false): array
    {
        $properties = ['company_id' => ['type' => 'string'], 'label' => ['type' => ['string', 'null'], 'maxLength' => 64], 'is_primary' => ['type' => 'boolean']];

        if (! $request) {
            $properties['id'] = ['type' => 'string'];
            $properties['contact_id'] = ['type' => 'string'];
        }

        return self::object($properties, $request ? ['company_id'] : array_keys($properties));
    }

    /**
     * @param  array<string, mixed>  $properties
     * @param  list<string>  $required
     * @return array<string, mixed>
     */
    private static function object(array $properties, array $required): array
    {
        return ['type' => 'object', 'additionalProperties' => false, 'properties' => $properties, 'required' => $required];
    }
}
