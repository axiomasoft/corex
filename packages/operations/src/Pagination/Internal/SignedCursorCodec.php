<?php

declare(strict_types=1);

namespace CoreX\Operations\Pagination\Internal;

use CoreX\Operations\Errors\Enums\OperationsErrorCode;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Operations\Pagination\Contracts\CursorCodec;
use CoreX\Operations\Pagination\Contracts\CursorKeyring;
use CoreX\Operations\Pagination\Contracts\OrderingRegistry;
use CoreX\Operations\Pagination\Data\CursorPayload;
use CoreX\Operations\Pagination\Data\CursorPosition;
use CoreX\Operations\Validation\Laravel\JsonCanonical;
use InvalidArgumentException;
use JsonException;
use Psr\Clock\ClockInterface;

/** `api-cursor/1`: `v1.<base64url(canonical-json)>.<base64url(hmac-sha256)>`. */
final readonly class SignedCursorCodec implements CursorCodec
{
    private const FORMAT_VERSION = 1;

    private const MAX_WIRE_BYTES = 4096;

    private const MAX_PAYLOAD_BYTES = 2048;

    private const MAX_JSON_DEPTH = 8;

    public function __construct(
        private ClockInterface $clock,
        private CursorKeyring $keyring,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @param  list<string>  $fields
     * @param  list<string|int|float|bool|null>  $values
     */
    public function encode(
        OrderingRegistry $registry,
        string $accountId,
        ?string $workspaceId,
        string $apiMajor,
        string $operationId,
        array $filters,
        array $fields,
        string $orderingId,
        array $values,
    ): string {
        $ordering = $registry->find($orderingId);

        if ($ordering === null) {
            throw new ApiError(OperationsErrorCode::OrderingVersionUnsupported, 400, 'Ordering version is not supported.');
        }

        $this->assertValues($values, $ordering['types']);
        $keyId = $this->keyring->currentKeyId();
        $secret = $this->keyring->verifyingSecret($keyId);

        if ($secret === null || $secret === '') {
            throw new InvalidArgumentException('Cursor signing key is not configured.');
        }

        $issuedAt = $this->clock->now()->getTimestamp();
        $payload = new CursorPayload(
            formatVersion: self::FORMAT_VERSION,
            ordering: $orderingId,
            direction: $ordering['direction'],
            values: $values,
            scopeHash: $this->scopeHash($accountId, $workspaceId, $apiMajor, $operationId, $filters, $fields),
            issuedAt: $issuedAt,
            expiresAt: $issuedAt + $this->keyring->ttlSeconds(),
            keyId: $keyId,
        );
        $canonical = JsonCanonical::encode($this->payloadArray($payload));
        $signature = hash_hmac('sha256', $canonical, $secret, true);

        return 'v1.'.$this->base64UrlEncode($canonical).'.'.$this->base64UrlEncode($signature);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  list<string>  $fields
     */
    public function decode(
        string $cursor,
        OrderingRegistry $registry,
        string $accountId,
        ?string $workspaceId,
        string $apiMajor,
        string $operationId,
        array $filters,
        array $fields,
        string $orderingId,
    ): CursorPosition {
        $payload = $this->open($cursor);
        $ordering = $registry->find($payload->ordering);

        if ($ordering === null) {
            throw new ApiError(OperationsErrorCode::OrderingVersionUnsupported, 400, 'Ordering version is not supported.');
        }

        if ($payload->formatVersion !== self::FORMAT_VERSION
            || $payload->ordering !== $orderingId
            || $payload->direction !== $ordering['direction']) {
            $this->invalid();
        }

        try {
            $this->assertValues($payload->values, $ordering['types']);
        } catch (InvalidArgumentException) {
            $this->invalid();
        }

        $now = $this->clock->now()->getTimestamp();

        if ($payload->expiresAt < $now || $payload->issuedAt > $now) {
            $this->invalid();
        }

        $expectedHash = $this->scopeHash($accountId, $workspaceId, $apiMajor, $operationId, $filters, $fields);

        if (! hash_equals($expectedHash, $payload->scopeHash)) {
            $this->invalid();
        }

        return new CursorPosition(
            ordering: $payload->ordering,
            direction: $payload->direction,
            fields: $ordering['fields'],
            types: $ordering['types'],
            values: $payload->values,
        );
    }

    private function open(string $cursor): CursorPayload
    {
        if (strlen($cursor) > self::MAX_WIRE_BYTES || preg_match('/\Av1\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\z/D', $cursor) !== 1) {
            $this->invalid();
        }

        [, $encodedPayload, $encodedSignature] = explode('.', $cursor, 3);
        $payloadBytes = $this->base64UrlDecode($encodedPayload);
        $signature = $this->base64UrlDecode($encodedSignature);

        if ($payloadBytes === null || $signature === null
            || strlen($payloadBytes) > self::MAX_PAYLOAD_BYTES
            || strlen($signature) !== 32) {
            $this->invalid();
        }

        try {
            $decoded = json_decode($payloadBytes, true, self::MAX_JSON_DEPTH, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->invalid();
        }

        if (! is_array($decoded) || array_is_list($decoded)) {
            $this->invalid();
        }

        $keyId = $decoded['keyId'] ?? null;

        if (! is_string($keyId) || $keyId === '') {
            $this->invalid();
        }

        $secret = $this->keyring->verifyingSecret($keyId);

        if ($secret === null || $secret === '') {
            $this->invalid();
        }

        $actual = hash_hmac('sha256', $payloadBytes, $secret, true);

        if (! hash_equals($actual, $signature)) {
            $this->invalid();
        }

        try {
            if (JsonCanonical::encode($decoded) !== $payloadBytes) {
                $this->invalid();
            }
        } catch (InvalidArgumentException) {
            $this->invalid();
        }

        $payload = $this->payloadFromArray($decoded);

        if ($payload === null || $payload->formatVersion !== self::FORMAT_VERSION) {
            $this->invalid();
        }

        return $payload;
    }

    /**
     * @param  list<string|int|float|bool|null>  $values
     * @param  list<string>  $types
     */
    private function assertValues(array $values, array $types): void
    {
        if (count($values) !== count($types)) {
            throw new InvalidArgumentException('Cursor value arity is invalid.');
        }

        foreach ($values as $index => $value) {
            $valid = match ($types[$index]) {
                'string' => is_string($value),
                'int' => is_int($value),
                'float' => is_float($value),
                'bool' => is_bool($value),
                default => false,
            };

            if (! $valid) {
                throw new InvalidArgumentException('Cursor value type is invalid.');
            }
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  list<string>  $fields
     */
    private function scopeHash(
        string $accountId,
        ?string $workspaceId,
        string $apiMajor,
        string $operationId,
        array $filters,
        array $fields,
    ): string {
        $selection = $fields;
        sort($selection);

        return hash('sha256', JsonCanonical::encode([
            'account_id' => $accountId,
            'api_major' => $apiMajor,
            'fields' => $selection,
            'filters' => $filters,
            'operation_id' => $operationId,
            'workspace_id' => $workspaceId,
        ]));
    }

    /** @return array<string, mixed> */
    private function payloadArray(CursorPayload $payload): array
    {
        return [
            'direction' => $payload->direction,
            'expiresAt' => $payload->expiresAt,
            'formatVersion' => $payload->formatVersion,
            'issuedAt' => $payload->issuedAt,
            'keyId' => $payload->keyId,
            'ordering' => $payload->ordering,
            'scopeHash' => $payload->scopeHash,
            'values' => $payload->values,
        ];
    }

    /** @param  array<string, mixed>  $decoded */
    private function payloadFromArray(array $decoded): ?CursorPayload
    {
        $keys = array_keys($decoded);
        sort($keys);

        if ($keys !== ['direction', 'expiresAt', 'formatVersion', 'issuedAt', 'keyId', 'ordering', 'scopeHash', 'values']) {
            return null;
        }

        $values = $decoded['values'];

        if (! is_int($decoded['formatVersion']) || ! is_string($decoded['ordering']) || $decoded['ordering'] === ''
            || ! is_string($decoded['direction']) || ! is_array($values) || ! array_is_list($values)
            || ! is_string($decoded['scopeHash']) || preg_match('/\A[0-9a-f]{64}\z/D', $decoded['scopeHash']) !== 1
            || ! is_int($decoded['issuedAt']) || ! is_int($decoded['expiresAt'])
            || ! is_string($decoded['keyId']) || $decoded['keyId'] === '') {
            return null;
        }

        $scalars = [];

        foreach ($values as $value) {
            if ($value !== null && ! is_string($value) && ! is_int($value) && ! is_float($value) && ! is_bool($value)) {
                return null;
            }

            $scalars[] = $value;
        }

        return new CursorPayload(
            formatVersion: $decoded['formatVersion'],
            ordering: $decoded['ordering'],
            direction: $decoded['direction'],
            values: $scalars,
            scopeHash: $decoded['scopeHash'],
            issuedAt: $decoded['issuedAt'],
            expiresAt: $decoded['expiresAt'],
            keyId: $decoded['keyId'],
        );
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): ?string
    {
        if ($value === '' || preg_match('/\A[A-Za-z0-9_-]+\z/D', $value) !== 1) {
            return null;
        }

        $decoded = base64_decode(strtr($value, '-_', '+/').str_repeat('=', (4 - strlen($value) % 4) % 4), true);

        return $decoded === false ? null : $decoded;
    }

    /** @return never */
    private function invalid(): void
    {
        throw new ApiError(OperationsErrorCode::CursorInvalid, 400, 'Cursor is invalid.');
    }
}
