<?php

declare(strict_types=1);

namespace App\BulkTransfer;

use App\Scenario\Scenario;
use CoreX\Context\Contracts\ScopeAdmission;
use CoreX\Context\Data\AdmissionTicket;
use CoreX\Context\Data\AttemptEvidence;
use CoreX\Context\Data\OperationKey;
use CoreX\Context\Enums\AdmissionKind;
use CoreX\Data\Contracts\DataScopeCodec;
use CoreX\Data\Data\DataScope;
use Illuminate\Database\Query\Builder;

final class CrashRecovery
{
    public const array BARRIERS = ['sink-committed', 'import-owner-committed', 'export-part-committed', 'export-published'];

    private static array $preexisting = [];

    public static function start(): void
    {
        self::$preexisting = self::unresolved()->pluck('ticket_id')->all();
    }

    public static function workerTickets(): array
    {
        return self::unresolved()->where('kind', AdmissionKind::LocalWrite->value)->whereNotIn('ticket_id', self::$preexisting)
            ->orderBy('ticket_id')->pluck('ticket_id')->all();
    }

    /** Reconcile only the identified test worker's local transaction, using its physical session and recovered owner evidence. */
    public static function reconcile(Scenario $test, DataScope $scope): array
    {
        $db = Runtime::tenant();
        $witness = [];
        foreach (self::BARRIERS as $name) {
            $barrier = Scenario::barrierRead($name);
            $session = $barrier['tenant_session'];
            $test->check($barrier['local_tickets'] !== [], 'The killed worker must supply its actual local transaction ticket.');
            $test->check(! $db->table('pg_stat_activity')->where('pid', $session)->where('datname', $db->getDatabaseName())->exists(),
                'A killed worker still owns a live PostgreSQL session.');
            $bulk = $db->table('sys_api_bulk_operations')->where('id', $barrier['operation'])->sole();
            $test->same('completed', $bulk->status, 'Cannot reconcile without the verified recovered Bulk operation.');
            $ref = Runtime::transfer($bulk->id);
            $job = $db->table('corex_transfer_jobs')->where('id', $ref)->sole();
            $test->same('completed', $job->state, 'Cannot reconcile without the independently recovered transfer receipt.');

            if ($job->mode === 'import') {
                $test->same(2, $db->table('corex_transfer_steps')->where('job_id', $ref)->whereNotNull('receipt')->count(),
                    'Recovered import is missing its bounded local write receipts.');
                $effect = Scenario::barrierRead('sink-committed');
                $test->same($effect['receipt'], Scenario::connection('sink')->table('bulk_sink_receipts')->where('effect_key', $effect['key'])->value('receipt'),
                    'Cannot reconcile without the original independent sink receipt.');
            } else {
                $manifest = $db->table('corex_export_manifests')->where(['job_id' => $ref, 'state' => 'published'])->sole();
                $test->same(2, $db->table('corex_export_parts')->where(['manifest_id' => $manifest->id, 'state' => 'verified'])->count(),
                    'Cannot reconcile without a complete verified publication.');
            }
            foreach ($barrier['local_tickets'] as $ticketId) {
                $test->check(! array_key_exists($ticketId, $witness), 'A crash ticket was attributed to two workers.');
                $witness[$ticketId] = ['barrier' => $name, 'session' => $session, 'operation' => $bulk->id, 'owner' => $ref];
            }
        }

        $actual = self::unresolved()->orderBy('ticket_id')->get();
        $ids = array_keys($witness);
        sort($ids);
        $test->same($ids, $actual->pluck('ticket_id')->all(), 'Route has an unresolved admission without a verified killed-process witness.');

        foreach ($actual as $row) {
            $ticketScope = app(DataScopeCodec::class)->decode(json_decode($row->scope, true, flags: JSON_THROW_ON_ERROR));
            $test->same(get_object_vars($scope), get_object_vars($ticketScope), 'Cannot reconcile a local ticket owned by a different work scope.');
            $test->same(AdmissionKind::LocalWrite->value, $row->kind, 'Provider effects must already have independent reconciliation receipts.');
            $test->same(null, $row->provider_identity, 'Local reconciliation must never touch provider evidence.');
            $ticket = new AdmissionTicket($row->ticket_id, $ticketScope, (int) $row->control_revision,
                new OperationKey($row->operation_id, (int) $row->attempt), AdmissionKind::LocalWrite);
            $proof = $witness[$row->ticket_id];
            app(ScopeAdmission::class)->settle($ticket, new AttemptEvidence(
                'reconciled', 'confirmed-worker-exit:'.$proof['session'].':'.$proof['owner'],
            ));
        }
        $test->same(0, self::unresolved()->count(), 'Reconciled local transaction tickets still block route drain.');

        return $witness;
    }

    private static function unresolved(): Builder
    {
        return Scenario::connection('control')->table('corex_scope_tickets')
            ->where('account_id', Scenario::BOXED)->whereIn('state', ['outstanding', 'unknown', 'conflicting']);
    }
}
