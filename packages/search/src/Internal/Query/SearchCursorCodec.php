<?php

declare(strict_types=1);

namespace CoreX\Search\Internal\Query;

use CoreX\Data\Data\DataScope;
use CoreX\Search\Data\SearchFilter;
use CoreX\Search\Data\SearchQuery;
use CoreX\Search\Data\SearchSnapshot;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

final readonly class SearchCursorCodec
{
    private const Cipher = 'aes-256-gcm';

    private const Provider = 'postgres-v1';

    public function __construct(private string $secret)
    {
        if (strlen($secret) < 32) {
            throw new InvalidArgumentException('Search cursor secret must contain at least 32 bytes.');
        }
    }

    public function encode(DataScope $scope, SearchQuery $query, SearchSnapshot $schema, string $id): string
    {
        if ($id === '' || strlen($id) > 128) {
            throw new InvalidArgumentException('Search cursor record identifier is invalid.');
        }

        try {
            $payload = json_encode([
                'id' => $id,
                'provider' => self::Provider,
                'query' => $this->queryFingerprint(query: $query),
                'schema' => $schema->schemaHash,
                'scope' => $this->scopeFingerprint(scope: $scope),
            ], JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Search cursor could not be encoded.', previous: $exception);
        }

        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($payload, self::Cipher, $this->key(), OPENSSL_RAW_DATA, $iv, $tag);

        if ($ciphertext === false) {
            throw new RuntimeException('Search cursor encryption failed.');
        }

        return 'v1.'.self::base64UrlEncode($iv.$tag.$ciphertext);
    }

    public function decode(DataScope $scope, SearchQuery $query, SearchSnapshot $schema, string $cursor): string
    {
        if (! str_starts_with($cursor, 'v1.')) {
            throw new InvalidArgumentException('Search cursor version is not supported.');
        }

        $encoded = substr($cursor, 3);
        $binary = self::base64UrlDecode($encoded);

        if ($binary === null || strlen($binary) < 29) {
            throw new InvalidArgumentException('Search cursor is malformed.');
        }

        $iv = substr($binary, 0, 12);
        $tag = substr($binary, 12, 16);
        $ciphertext = substr($binary, 28);
        $payload = openssl_decrypt($ciphertext, self::Cipher, $this->key(), OPENSSL_RAW_DATA, $iv, $tag);

        if ($payload === false) {
            throw new InvalidArgumentException('Search cursor signature is invalid.');
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($payload, true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Search cursor payload is invalid.', previous: $exception);
        }

        if (! is_array($decoded)
            || ! is_string($decoded['id'] ?? null)
            || ! is_string($decoded['provider'] ?? null)
            || ! is_string($decoded['query'] ?? null)
            || ! is_string($decoded['schema'] ?? null)
            || ! is_string($decoded['scope'] ?? null)
            || $decoded['provider'] !== self::Provider
            || ! hash_equals($this->queryFingerprint(query: $query), $decoded['query'])
            || ! hash_equals($schema->schemaHash, $decoded['schema'])
            || ! hash_equals($this->scopeFingerprint(scope: $scope), $decoded['scope'])
            || $decoded['id'] === ''
            || strlen($decoded['id']) > 128) {
            throw new InvalidArgumentException('Search cursor does not match this query scope.');
        }

        return $decoded['id'];
    }

    private static function base64UrlDecode(string $value): ?string
    {
        if ($value === '' || preg_match('/\A[A-Za-z0-9_-]+\z/D', $value) !== 1) {
            return null;
        }

        return base64_decode(strtr($value, '-_', '+/').str_repeat('=', (4 - strlen($value) % 4) % 4), true) ?: null;
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function key(): string
    {
        return hash('sha256', $this->secret, true);
    }

    private function queryFingerprint(SearchQuery $query): string
    {
        try {
            return hash('sha256', json_encode([
                'entity' => $query->entity,
                'filters' => array_map(
                    static fn (SearchFilter $filter): array => ['field' => $filter->field, 'values' => $filter->values],
                    $query->filters,
                ),
                'schemaHash' => $query->schemaHash,
                'text' => $query->text,
            ], JSON_THROW_ON_ERROR));
        } catch (JsonException $exception) {
            throw new RuntimeException('Search query cursor binding could not be encoded.', previous: $exception);
        }
    }

    private function scopeFingerprint(DataScope $scope): string
    {
        return hash('sha256', $scope->connectionIdentity."\0".$scope->actorId."\0".$scope->restoreEpoch);
    }
}
