<?php

declare(strict_types=1);

namespace CoreX\Operations\Tests\Unit;

use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Invocation\Contracts\RequestContext;
use CoreX\Operations\Authorization\Laravel\DeclaredFieldAuthorizer;
use CoreX\Operations\Definition\Data\Operation;
use CoreX\Operations\Testing\StubOperationHandler;
use PHPUnit\Framework\TestCase;

final class DeclaredFieldAuthorizerTest extends TestCase
{
    public function test_recursive_projection_keeps_later_fields_list_positions_and_escaped_keys(): void
    {
        $operation = $this->operation(read: ['/profile/name', '/items/1/name', 'a~/b', 'after', 'empty']);
        $context = $this->createStub(RequestContext::class);
        $policy = new DeclaredFieldAuthorizer;

        self::assertSame([
            'profile' => ['name' => 'Ada'],
            'items' => [null, ['name' => 'second']],
            'a~/b' => 'escaped',
            'after' => 'last',
            'empty' => [],
        ], DeclaredFieldAuthorizer::prune(operation: $operation, context: $context, authorizer: $policy, data: [
            'hidden' => 'first',
            'profile' => ['secret' => 'hidden', 'name' => 'Ada'],
            'items' => [['name' => 'first'], ['secret' => 'hidden', 'name' => 'second']],
            'a~/b' => 'escaped',
            'after' => 'last',
            'empty' => [],
            'profiled' => 'sibling',
        ]));
    }

    public function test_declarations_cover_descendants_without_granting_sibling_prefixes(): void
    {
        $policy = new DeclaredFieldAuthorizer;
        $operation = $this->operation(read: ['/profile', 'a~/b'], write: ['/profile', 'a~/b']);
        $context = $this->createStub(RequestContext::class);

        foreach (['/profile', '/profile/name', '/a~0~1b', '/a~0~1b/child'] as $allowed) {
            self::assertTrue($policy->allowsRead(operation: $operation, context: $context, path: $allowed));
            self::assertTrue($policy->allowsWrite(operation: $operation, context: $context, path: $allowed));
        }
        foreach (['/profiled', '/profile-name', '/a~/b', '/unrelated'] as $denied) {
            self::assertFalse($policy->allowsRead(operation: $operation, context: $context, path: $denied));
            self::assertFalse($policy->allowsWrite(operation: $operation, context: $context, path: $denied));
        }
    }

    public function test_write_checks_walk_all_nested_leaves_and_report_the_first_forbidden_pointer(): void
    {
        $context = $this->createStub(RequestContext::class);
        $policy = new DeclaredFieldAuthorizer;
        $operation = $this->operation(write: ['/profile/name', '/items', 'a~/b']);

        self::assertNull(DeclaredFieldAuthorizer::firstForbiddenWrite(
            operation: $operation,
            context: $context,
            authorizer: $policy,
            input: ['profile' => ['name' => 'Ada'], 'items' => [['name' => 'first'], []], 'a~/b' => 7],
        ));
        self::assertSame('/profile/secret', DeclaredFieldAuthorizer::firstForbiddenWrite(
            operation: $operation, context: $context, authorizer: $policy,
            input: ['profile' => ['name' => 'Ada', 'secret' => 'hidden'], 'other' => 'later'],
        ));
        self::assertSame('/later', DeclaredFieldAuthorizer::firstForbiddenWrite(
            operation: $operation, context: $context, authorizer: $policy,
            input: ['profile' => ['name' => 'Ada'], 'later' => 'hidden'],
        ));
    }

    public function test_null_declarations_allow_all_and_empty_declarations_deny_all(): void
    {
        $context = $this->createStub(RequestContext::class);
        $policy = new DeclaredFieldAuthorizer;
        $data = ['profile' => ['name' => 'Ada'], 'items' => [7, []]];

        self::assertSame($data, DeclaredFieldAuthorizer::prune(
            operation: $this->operation(), context: $context, authorizer: $policy, data: $data,
        ));
        self::assertNull(DeclaredFieldAuthorizer::firstForbiddenWrite(
            operation: $this->operation(), context: $context, authorizer: $policy, input: $data,
        ));
        self::assertSame([], DeclaredFieldAuthorizer::prune(
            operation: $this->operation(read: []), context: $context, authorizer: $policy, data: $data,
        ));
        self::assertSame('/profile/name', DeclaredFieldAuthorizer::firstForbiddenWrite(
            operation: $this->operation(write: []), context: $context, authorizer: $policy, input: $data,
        ));
    }

    /** @param list<string>|null $read
     * @param  list<string>|null  $write
     */
    private function operation(?array $read = null, ?array $write = null): Operation
    {
        return new Operation(
            module: 'example/notes', id: 'notes.read', version: '1', method: 'GET', path: '/notes',
            ability: 'notes.read', handler: StubOperationHandler::class,
            requestSchema: JsonDocuments::capture([]), responseSchema: JsonDocuments::capture([]),
            inputRules: [], successStatus: 200, readFields: $read, writeFields: $write,
        );
    }
}
