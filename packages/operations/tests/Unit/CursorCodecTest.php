<?php

declare(strict_types=1);

namespace CoreX\Operations\Tests\Unit;

use CoreX\Operations\Definition\Contracts\OperationHandler;
use CoreX\Operations\Definition\Enums\OperationMode;
use CoreX\Operations\Definition\Operation;
use CoreX\Operations\Errors\ApiError;
use CoreX\Operations\Invocation\Contracts\RequestContext;
use CoreX\Operations\Pagination\Contracts\CursorKeyring;
use CoreX\Operations\Pagination\Data\CursorPage;
use CoreX\Operations\Pagination\OrderingRegistry;
use CoreX\Operations\Pagination\SignedCursorCodec;
use CoreX\Operations\Tests\TestCase;
use CoreX\Operations\Validation\JsonCanonical;
use CoreX\Time\FrozenClock;
use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;
use LogicException;

final class CursorCodecTest extends TestCase
{
    private const SECRET = 'cursor-test-secret';

    private FrozenClock $clock;

    private ArrayCursorKeyring $keys;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clock = new FrozenClock(new DateTimeImmutable('2026-09-25T12:00:00+00:00'));
        $this->keys = new ArrayCursorKeyring('primary', 3600, ['primary' => self::SECRET, 'previous' => 'old-secret']);
    }

    public function test_golden_wire_round_trips_and_page_encodes_after_assembly(): void
    {
        $codec = $this->codec();
        $registry = $this->registry();
        $cursor = $codec->encode(...$this->scope($registry, [10, 'a']));
        $canonical = JsonCanonical::encode([
            'direction' => 'asc',
            'expiresAt' => 1790337600 + 3600,
            'formatVersion' => 1,
            'issuedAt' => 1790337600,
            'keyId' => 'primary',
            'ordering' => 'notes.list.v1',
            'scopeHash' => $this->scopeHash(),
            'values' => [10, 'a'],
        ]);
        $expected = 'v1.'.$this->base64Url($canonical).'.'.$this->base64Url(hash_hmac('sha256', $canonical, self::SECRET, true));

        $this->assertSame($expected, $cursor);

        $position = $codec->decode($cursor, ...$this->decodeScope($registry));

        $this->assertSame(['created_at', 'id'], $position->fields);
        $this->assertSame([10, 'a'], $position->values);
        $this->assertTrue($position->admits([10, 'b']));
        $this->assertFalse($position->admits([10, 'a']));
        $this->assertFalse($position->admits([9, 'z']));

        $page = CursorPage::assemble(
            items: [['id' => 'a']],
            nextValues: [10, 'b'],
            codec: $codec,
            registry: $registry,
            accountId: 'acct-1',
            workspaceId: 'ws-1',
            apiMajor: 'v1',
            operationId: 'notes.list',
            filters: ['status' => 'open'],
            fields: ['id', 'title'],
            orderingId: 'notes.list.v1',
        );

        $this->assertSame([['id' => 'a']], $page->items);
        $this->assertNotNull($page->next);
        $this->assertSame([10, 'b'], $codec->decode($page->next, ...$this->decodeScope($registry))->values);
        $this->assertNull(CursorPage::assemble([['id' => 'a']], null, $codec, $registry, 'acct-1', 'ws-1', 'v1', 'notes.list', ['status' => 'open'], ['id', 'title'], 'notes.list.v1')->next);
    }

    public function test_tamper_unknown_and_expired_key_and_expired_cursor_are_invalid(): void
    {
        $codec = $this->codec();
        $registry = $this->registry();
        $cursor = $codec->encode(...$this->scope($registry, [10, 'a']));
        $parts = explode('.', $cursor);

        foreach ([
            'v2.'.$parts[1].'.'.$parts[2],
            'v1.'.$this->flip($parts[1]).'.'.$parts[2],
            'v1.'.$parts[1].'.'.$this->flip($parts[2]),
        ] as $tampered) {
            $this->expectCursorInvalid(fn () => $codec->decode($tampered, ...$this->decodeScope($registry)));
        }

        $this->keys->current = 'missing';
        $this->keys->secrets = ['missing' => 'other'];
        $this->expectCursorInvalid(fn () => $codec->decode($cursor, ...$this->decodeScope($registry)));

        $this->keys->current = 'primary';
        $this->keys->secrets = ['primary' => self::SECRET];
        $this->clock->advance(new DateInterval('PT3601S'));
        $this->expectCursorInvalid(fn () => $codec->decode($cursor, ...$this->decodeScope($registry)));
    }

    public function test_previous_key_verifies_until_it_leaves_the_ring(): void
    {
        $codec = $this->codec();
        $registry = $this->registry();
        $this->keys->current = 'previous';
        $cursor = $codec->encode(...$this->scope($registry, [10, 'a']));
        $this->keys->current = 'primary';

        $this->assertSame([10, 'a'], $codec->decode($cursor, ...$this->decodeScope($registry))->values);

        unset($this->keys->secrets['previous']);
        $this->expectCursorInvalid(fn () => $codec->decode($cursor, ...$this->decodeScope($registry)));
    }

    public function test_scope_direction_order_arity_and_type_mismatches_are_invalid(): void
    {
        $codec = $this->codec();
        $registry = $this->registry();
        $cursor = $codec->encode(...$this->scope($registry, [10, 'a']));

        $this->expectCursorInvalid(fn () => $codec->decode($cursor, $registry, 'acct-2', 'ws-1', 'v1', 'notes.list', ['status' => 'open'], ['id', 'title'], 'notes.list.v1'));
        $this->expectCursorInvalid(fn () => $codec->decode($cursor, $registry, 'acct-1', 'ws-2', 'v1', 'notes.list', ['status' => 'open'], ['id', 'title'], 'notes.list.v1'));
        $this->expectCursorInvalid(fn () => $codec->decode($cursor, $registry, 'acct-1', 'ws-1', 'v1', 'notes.list', ['status' => 'closed'], ['id', 'title'], 'notes.list.v1'));
        $this->expectCursorInvalid(fn () => $codec->decode($cursor, $registry, 'acct-1', 'ws-1', 'v1', 'notes.list', ['status' => 'open'], ['title'], 'notes.list.v1'));
        $this->expectCursorInvalid(fn () => $codec->decode($cursor, $registry, 'acct-1', 'ws-1', 'v2', 'notes.list', ['status' => 'open'], ['id', 'title'], 'notes.list.v1'));
        $this->expectCursorInvalid(fn () => $codec->decode($cursor, $registry, 'acct-1', 'ws-1', 'v1', 'notes.show', ['status' => 'open'], ['id', 'title'], 'notes.list.v1'));

        $desc = $this->registry(direction: 'desc');
        $this->expectCursorInvalid(fn () => $codec->decode($cursor, $desc, 'acct-1', 'ws-1', 'v1', 'notes.list', ['status' => 'open'], ['id', 'title'], 'notes.list.v1'));
        $this->expectCursorInvalid(fn () => $codec->decode($cursor, $registry, 'acct-1', 'ws-1', 'v1', 'notes.list', ['status' => 'open'], ['id', 'title'], 'notes.show.v1'));

        $wider = $this->registry(fields: ['created_at', 'id', 'extra'], types: ['int', 'string', 'string'], unique: 'extra');
        $this->expectCursorInvalid(fn () => $codec->decode($cursor, $wider, 'acct-1', 'ws-1', 'v1', 'notes.list', ['status' => 'open'], ['id', 'title'], 'notes.list.v1'));
        $stringTyped = $this->registry(types: ['string', 'string']);
        $this->expectCursorInvalid(fn () => $codec->decode($cursor, $stringTyped, 'acct-1', 'ws-1', 'v1', 'notes.list', ['status' => 'open'], ['id', 'title'], 'notes.list.v1'));

        $this->expectOrderingUnsupported(fn () => $codec->decode($cursor, OrderingRegistry::fromOperations([]), 'acct-1', 'ws-1', 'v1', 'notes.list', ['status' => 'open'], ['id', 'title'], 'notes.list.v1'));
    }

    public function test_reversed_operation_order_keeps_the_registry_bytes(): void
    {
        $first = $this->operation('notes.list', 'notes.list.v1');
        $second = $this->operation('notes.show', 'notes.show.v1', fields: ['id'], types: ['string']);

        $forward = OrderingRegistry::fromOperations([$first, $second]);
        $reverse = OrderingRegistry::fromOperations([$second, $first]);

        $this->assertSame($forward->canonicalBytes(), $reverse->canonicalBytes());
        $this->expectException(InvalidArgumentException::class);
        OrderingRegistry::fromOperations([$this->operation('notes.list', 'notes.list.v1', unique: 'created_at')]);
    }

    private function codec(): SignedCursorCodec
    {
        return new SignedCursorCodec($this->clock, $this->keys);
    }

    private function registry(string $direction = 'asc', array $fields = ['created_at', 'id'], array $types = ['int', 'string'], string $unique = 'id'): OrderingRegistry
    {
        return OrderingRegistry::fromOperations([
            $this->operation('notes.list', 'notes.list.v1', $direction, $fields, $types, $unique),
            $this->operation('notes.show', 'notes.show.v1', fields: ['id'], types: ['string']),
        ]);
    }

    /** @param  list<string|int|float|bool|null>  $values
     * @return array{0: OrderingRegistry, 1: string, 2: string, 3: string, 4: string, 5: array<string, string>, 6: list<string>, 7: string, 8: list<string|int|float|bool|null>}
     */
    private function scope(OrderingRegistry $registry, array $values): array
    {
        return [$registry, 'acct-1', 'ws-1', 'v1', 'notes.list', ['status' => 'open'], ['title', 'id'], 'notes.list.v1', $values];
    }

    /** @return array{0: OrderingRegistry, 1: string, 2: string, 3: string, 4: string, 5: array<string, string>, 6: list<string>, 7: string} */
    private function decodeScope(OrderingRegistry $registry): array
    {
        return [$registry, 'acct-1', 'ws-1', 'v1', 'notes.list', ['status' => 'open'], ['id', 'title'], 'notes.list.v1'];
    }

    private function scopeHash(): string
    {
        return hash('sha256', JsonCanonical::encode([
            'account_id' => 'acct-1',
            'api_major' => 'v1',
            'fields' => ['id', 'title'],
            'filters' => ['status' => 'open'],
            'operation_id' => 'notes.list',
            'workspace_id' => 'ws-1',
        ]));
    }

    /** @param  list<string>  $fields
     * @param  list<string>  $types
     */
    private function operation(
        string $id,
        string $ordering,
        string $direction = 'asc',
        array $fields = ['created_at', 'id'],
        array $types = ['int', 'string'],
        string $unique = 'id',
    ): Operation {
        return new Operation(
            module: 'example/notes',
            id: $id,
            version: 'v1',
            method: 'GET',
            path: '/'.$id,
            ability: 'notes.read',
            handler: CursorHandler::class,
            requestSchema: [],
            responseSchema: [],
            inputRules: [],
            successStatus: 200,
            mode: OperationMode::Query,
            pagination: [
                'ordering' => $ordering,
                'direction' => $direction,
                'fields' => $fields,
                'types' => $types,
                'unique' => $unique,
            ],
        );
    }

    private function expectCursorInvalid(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected cursor_invalid.');
        } catch (ApiError $error) {
            $this->assertSame('cursor_invalid', $error->errorCode);
        }
    }

    private function expectOrderingUnsupported(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected ordering_version_unsupported.');
        } catch (ApiError $error) {
            $this->assertSame('ordering_version_unsupported', $error->errorCode);
        }
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function flip(string $segment): string
    {
        $chars = str_split($segment);
        $chars[0] = $chars[0] === 'A' ? 'B' : 'A';

        return implode('', $chars);
    }
}

/** @internal */
final class ArrayCursorKeyring implements CursorKeyring
{
    /** @param  array<string, string>  $secrets */
    public function __construct(
        public string $current,
        public int $ttl,
        public array $secrets,
    ) {}

    public function currentKeyId(): string
    {
        return $this->current;
    }

    public function ttlSeconds(): int
    {
        return $this->ttl;
    }

    public function verifyingSecret(string $keyId): ?string
    {
        return $this->secrets[$keyId] ?? null;
    }
}

/** @internal */
final class CursorHandler implements OperationHandler
{
    public function handle(RequestContext $context, array $input, array $parameters): array
    {
        throw new LogicException('Cursor tests do not execute handlers.');
    }
}
