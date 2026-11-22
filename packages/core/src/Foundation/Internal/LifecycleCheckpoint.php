<?php

declare(strict_types=1);

namespace CoreX\Foundation\Internal;

use CoreX\Data\Data\DataScope;
use CoreX\Foundation\Data\CleanupAction;
use CoreX\Foundation\Data\OwnerLease;
use CoreX\Foundation\Exceptions\LifecycleBlocked;
use Packman\Attributes\Internal;

/** Validate durable checkpoint data before interpreting it as cleanup authority. */
#[Internal]
final readonly class LifecycleCheckpoint
{
    public string $state;

    public string $planId;

    public string $requestKey;

    public string $module;

    public string $action;

    public string $digest;

    public bool $cleanupStarted;

    public ?string $transitionResult;

    /** @var array<string,mixed> */
    public array $scope;

    /** @var array{reason:string,cascade:bool,targets:list<string>} */
    public array $arguments;

    /** @var array<string,mixed> */
    public array $leases;

    /** @var array<string,mixed> */
    public array $actions;

    /** @var array<string,mixed> */
    public array $receipts;

    /** @var array<array-key,mixed> */
    public array $inventory;

    /** @var array<array-key,mixed> */
    public array $cursor;

    /** @var list<mixed> */
    public array $blockers;

    public function __construct(object $row)
    {
        $record = get_object_vars($row);
        $this->planId = self::text(values: $record, key: 'plan_id');
        $this->requestKey = self::text(values: $record, key: 'request_key');
        $this->state = self::text(values: $record, key: 'state');
        $this->cleanupStarted = match ($record['cleanup_started'] ?? null) {
            true, 1, '1', 't' => true,
            false, 0, '0', 'f' => false,
            default => throw new LifecycleBlocked('Invalid cleanup checkpoint.'),
        };
        $this->module = self::text(values: $record, key: 'module');
        $this->action = self::text(values: $record, key: 'action');
        $this->digest = self::text(values: $record, key: 'digest');
        $this->transitionResult = ($record['transition_result'] ?? null) === null ? null : self::text(values: $record, key: 'transition_result');
        $this->scope = self::map(self::document(record: $record, field: 'scope'));
        $args = self::document(record: $record, field: 'arguments');
        $targets = $args['targets'] ?? null;
        $cascade = $args['cascade'] ?? null;

        if (! is_bool($cascade) || ! is_array($targets) || ! array_is_list($targets) || $targets === []) {
            throw new LifecycleBlocked('Invalid lifecycle transition arguments.');
        }
        $this->arguments = ['reason' => self::text(values: $args, key: 'reason'), 'cascade' => $cascade,
            'targets' => array_map(static fn (mixed $name): string => is_string($name) ? $name : throw new LifecycleBlocked('Invalid module target.'), $targets)];
        $this->leases = self::map(self::document(record: $record, field: 'leases'));
        $this->actions = self::map(self::document(record: $record, field: 'actions'));
        $this->receipts = self::map(self::document(record: $record, field: 'receipts'));
        $this->inventory = self::document(record: $record, field: 'inventory');
        $this->cursor = self::document(record: $record, field: 'cursor');
        $blockers = self::document(record: $record, field: 'blockers');

        if (! array_is_list($blockers)) {
            throw new LifecycleBlocked('Invalid lifecycle blocker list.');
        }
        $this->blockers = $blockers;
    }

    /** @param array<array-key,mixed> $values */
    public static function text(array $values, string $key): string
    {
        $value = $values[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : throw new LifecycleBlocked('Invalid lifecycle checkpoint field '.$key);
    }

    /** @param array<array-key,mixed> $record
     * @return array<array-key,mixed> */
    private static function document(array $record, string $field): array
    {
        $value = json_decode(self::text(values: $record, key: $field), true, flags: JSON_THROW_ON_ERROR);

        return is_array($value) ? $value : throw new LifecycleBlocked('Invalid lifecycle document '.$field);
    }

    /** @return array<string,mixed> */
    public static function map(mixed $value): array
    {
        if (! is_array($value)) {
            throw new LifecycleBlocked('Invalid lifecycle checkpoint object.');
        }
        $map = [];
        foreach ($value as $key => $item) {
            if (! is_string($key)) {
                throw new LifecycleBlocked('Invalid lifecycle checkpoint member.');
            }
            $map[$key] = $item;
        }

        return $map;
    }

    public static function cleanupAction(mixed $value): CleanupAction
    {
        $a = self::map($value);

        return new CleanupAction(owner: self::text(values: $a, key: 'owner'), namespace: self::text(values: $a, key: 'namespace'), resourceKind: self::text(values: $a, key: 'resourceKind'),
            resourceId: self::text(values: $a, key: 'resourceId'), version: self::text(values: $a, key: 'version'), effectKey: self::text(values: $a, key: 'effectKey'));
    }

    public static function lease(mixed $value): OwnerLease
    {
        $l = self::map($value);

        return new OwnerLease(owner: self::text(values: $l, key: 'owner'), namespace: self::text(values: $l, key: 'namespace'), revision: self::text(values: $l, key: 'revision'), token: self::text(values: $l, key: 'token'));
    }

    public static function blockerScope(mixed $value): DataScope
    {
        $s = self::map($value);
        $workspace = $s['workspaceId'] ?? null;
        $generation = $s['bindingGeneration'] ?? null;

        if (! is_int($generation) || ($workspace !== null && ! is_string($workspace))) {
            throw new LifecycleBlocked('Invalid saved blocker scope.');
        }

        return new DataScope(deploymentId: self::text(values: $s, key: 'deploymentId'), accountId: self::text(values: $s, key: 'accountId'), workspaceId: $workspace,
            bindingId: self::text(values: $s, key: 'bindingId'), bindingGeneration: $generation, accountIncarnation: self::text(values: $s, key: 'accountIncarnation'), restoreEpoch: self::text(values: $s, key: 'restoreEpoch'),
            principalKind: self::text(values: $s, key: 'principalKind'), principalId: self::text(values: $s, key: 'principalId'));
    }
}
