<?php

declare(strict_types=1);

namespace Flex\Crm\Context\Internal;

use Flex\Crm\Activity\Models\Activity;
use Flex\Crm\Company\Models\Company;
use Flex\Crm\Contact\Models\Contact;
use Flex\Crm\Context\Channels;
use Flex\Crm\Context\ContextGuard;
use Flex\Crm\Deal\Models\Deal;
use Flex\Crm\Internal\Value;
use Flex\Crm\Lead\Models\Lead;
use Flex\Crm\Support\CrmOptions;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Packman\Attributes\Internal;

/** @internal Saving observer: invariant enforcement for direct Eloquent writes. */
#[Internal]
final readonly class RecordRules
{
    public function __construct(
        private CrmOptions $options,
    ) {}

    public function saving(Company|Contact|Lead|Deal|Activity $record): void
    {
        $guard = resolve(ContextGuard::class);
        $ctx = $guard->current(lock: $record->getConnection()->transactionLevel() > 0);

        if ($record->getConnection()->getName() !== $ctx->connectionName) {
            throw new AuthorizationException('CRM writes require the execution context connection.');
        }
        $workspace = $record->getAttribute('workspace_id');

        if ($workspace !== $guard->workspace(ctx: $ctx, accountLevel: $workspace === null)) {
            throw new AuthorizationException('CRM cannot write to another workspace.');
        }

        if (! $record->exists) {
            $record->setAttribute('created_by', $ctx->actor->toArray());
        } elseif ($record->isDirty(['created_by', 'workspace_id'])) {
            throw new AuthorizationException('CRM creation provenance and workspace are immutable.');
        }
        $owner = $record->getAttribute('owner_id');

        if ($owner !== null) {
            $guard->user(ctx: $ctx, id: Value::text($owner), lock: $record->getConnection()->transactionLevel() > 0);
        }
        $source = $record->getAttribute('source_id');

        if ($source !== null) {
            $guard->id(Value::text($source));
            $query = $record->getConnection()->table('crm_sources')->where('id', $source)->where('is_active', true);

            if ($record->getConnection()->transactionLevel() > 0) {
                $query->sharedLock();
            }
            $found = $query->first();

            if ($found === null || ($found->workspace_id !== null && $found->workspace_id !== $workspace)) {
                throw new AuthorizationException('CRM source is not available in this record scope.');
            }
        }

        if ($record instanceof Lead || $record instanceof Deal || $record instanceof Activity) {
            $this->text(record: $record, field: $record instanceof Activity ? 'subject' : 'title', length: 255, required: ! ($record instanceof Activity));

            return;
        }
        foreach (['phones', 'emails'] as $field) {
            $value = $record->getAttribute($field) ?? [];

            if (! is_array($value)) {
                $this->invalid($field);
            }
            $record->setAttribute($field, Channels::normalize(input: $value, phone: $field === 'phones'));
        }

        if ($record instanceof Company) {
            foreach (['name' => 255, 'legal_name' => 255, 'domain' => 190, 'inn' => 12, 'kpp' => 9, 'ogrn' => 15] as $field => $length) {
                $this->text(record: $record, field: $field, length: $length, required: $field === 'name');
            }
        } else {
            foreach (['name_first' => 120, 'name_last' => 120, 'name_middle' => 120, 'job_title' => 190] as $field => $length) {
                $this->text(record: $record, field: $field, length: $length);
            }
            $name = implode(' ', array_filter([$record->name_last, $record->name_first, $record->name_middle], static fn (?string $value): bool => $value !== null));

            if ($name === '') {
                $channels = $record->emails !== [] ? $record->emails : $record->phones;
                $preferred = array_values(array_filter($channels, static fn (array $channel): bool => $channel['is_primary']));
                $name = $preferred[0]['norm'] ?? $channels[0]['norm'] ?? '';
            }

            if ($name === '') {
                $this->invalid('display_name');
            }
            $record->display_name = mb_substr($name, 0, 255);
        }
        $search = implode(' ', array_filter([
            $record instanceof Company ? $record->name : $record->display_name,
            $record instanceof Company ? $record->legal_name : $record->job_title,
            $record instanceof Company ? $record->inn : null,
            ...array_column($record->phones, 'norm'), ...array_column($record->emails, 'norm'),
        ], static fn (?string $value): bool => $value !== null && $value !== '' && $value !== '0'));

        if ($record->getConnection()->getDriverName() === 'pgsql') {
            $row = $record->getConnection()->selectOne('SELECT to_tsvector(?::regconfig, ?)::text AS value', [$this->options->searchLanguage, $search]);

            if (! is_object($row)) {
                throw new AuthorizationException('CRM search normalization returned no row.');
            }
            $record->setAttribute('search', Value::field($row, 'value'));
        } else {
            $record->setAttribute('search', $search);
        }
    }

    private function text(Model $record, string $field, int $length, bool $required = false): void
    {
        $value = $record->getAttribute($field);

        if ($value !== null && (! is_string($value) || ! mb_check_encoding($value, 'UTF-8'))) {
            $this->invalid($field);
        }
        $value = $value === null ? null : trim((string) preg_replace('/\s+/u', ' ', $value));

        if ($value === '') {
            $value = null;
        }

        if (($required && $value === null) || ($value !== null && mb_strlen($value) > $length)) {
            $this->invalid($field);
        }
        $record->setAttribute($field, $value);
    }

    private function invalid(string $field): never
    {
        throw ValidationException::withMessages([$field => 'Invalid CRM value or missing required nonempty value.']);
    }
}
