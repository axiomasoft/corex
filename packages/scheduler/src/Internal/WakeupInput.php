<?php

declare(strict_types=1);

namespace CoreX\Scheduler\Internal;

use CoreX\Data\Contracts\DataScopeCodec;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\JsonDocument;
use CoreX\Scheduling\Data\WakeupRequest;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Packman\Attributes\Internal;
use RuntimeException;
use stdClass;

#[Internal]
final readonly class WakeupInput
{
    public const string PROOF_OWNER = 'corex.scheduler';

    public static function proofReference(string $id, string $digest): string
    {
        return hash('sha256', json_encode([$id, $digest], JSON_THROW_ON_ERROR));
    }

    public function __construct(private DataScopeCodec $codec) {}

    /** @return array<string, scalar|null> */
    public function scope(DataScope $scope): array
    {
        $fields = $this->codec->encode($scope);
        ksort($fields, SORT_STRING);

        return $fields;
    }

    public static function workspace(DataScope $scope): string
    {
        return $scope->workspaceId === null ? 'account' : 'workspace:'.$scope->workspaceId;
    }

    public static function identity(Connection $db, DataScope $scope, string $kind, string $key): Builder
    {
        return $db->table('sys_wakeup_requests')
            ->where('account_id', $scope->accountId)
            ->where('account_incarnation', $scope->accountIncarnation)
            ->where('workspace_scope_key', self::workspace($scope))
            ->where('kind', $kind)
            ->where('request_key', $key);
    }

    /** UTC including all six fractional digits on both PostgreSQL and SQLite. */
    public static function time(DateTimeImmutable $time): string
    {
        return $time->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.uP');
    }

    public static function instant(mixed $value): DateTimeImmutable
    {
        return (new DateTimeImmutable(Value::text($value), new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('UTC'));
    }

    /** @param array<string, mixed> $data */
    public function digest(WakeupRequest $request, array $data): string
    {
        return hash('sha256', json_encode([
            'wakeup-request/3',
            $this->scope($request->scope),
            $request->kind,
            $request->handlerVersion,
            $request->requestKey,
            self::time($request->fireAt),
            $request->window === null ? null : [
                self::time($request->window->start),
                self::time($request->window->end),
                $request->window->period,
            ],
            (object) $data,
        ], JSON_THROW_ON_ERROR));
    }

    /** @return array<string, mixed> */
    public static function payload(JsonDocument $document): array
    {
        $value = json_decode($document->canonicalJson, flags: JSON_THROW_ON_ERROR);

        if ($value === []) {
            $value = new stdClass;
        }

        if (! $value instanceof stdClass) {
            throw new RuntimeException('Wakeup payload must be an object map.');
        }
        $value = self::canonical(value: $value, depth: 0);

        if (strlen(json_encode($value, JSON_THROW_ON_ERROR)) > 65_536) {
            throw new RuntimeException('Wakeup payload exceeds 64KiB.');
        }

        return Value::map((array) $value);
    }

    private static function canonical(mixed $value, int $depth): mixed
    {
        if ($depth > 8 || is_float($value)) {
            throw new RuntimeException('Unsupported wakeup payload value or nesting.');
        }

        if (! is_array($value) && ! $value instanceof stdClass) {
            return $value;
        }
        $values = (array) $value;

        if (count($values) > 256) {
            throw new RuntimeException('Wakeup payload contains too many values.');
        }

        if ($value instanceof stdClass) {
            ksort($values, SORT_STRING);
            foreach (array_keys($values) as $key) {
                if (! is_string($key) || $key === '') {
                    throw new RuntimeException('Wakeup payload contains an unsupported key.');
                }
            }
        }
        foreach ($values as $key => $item) {
            $values[$key] = self::canonical(value: $item, depth: $depth + 1);
        }

        return is_array($value) ? $values : (object) $values;
    }
}
