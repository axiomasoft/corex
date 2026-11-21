<?php

declare(strict_types=1);

namespace App\BulkTransfer;

use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\Data\WriteToken;
use CoreX\ImportExport\Contracts\TransferSink;
use CoreX\ImportExport\Data\CanonicalRecord;
use CoreX\ImportExport\Data\ConflictRule;
use CoreX\ImportExport\Data\ImportDecision;
use CoreX\ImportExport\Data\TargetResult;
use RuntimeException;

final class PostgresEquipmentSink implements TransferSink
{
    public function preview(DataScope $scope, CanonicalRecord $record, ConflictRule $rule): ImportDecision
    {
        return new ImportDecision('apply', null);
    }

    public function apply(DataScope $scope, CanonicalRecord $record, ConflictRule $rule, WriteToken $token): TargetResult
    {
        if ($rule->kind !== 'create-only' || $record->entity !== InputIdentity::ENTITY || $record->payloadHash !== $token->payloadHash) {
            throw new RuntimeException('The independent equipment sink received a different contract.');
        }
        $answer = $this->call('apply', $scope, $token, ['id' => $record->externalId, 'serial' => $record->values[InputIdentity::SERIAL]]);

        if (getenv('COREX_BULK_MODE') === 'import-send') {
            BarrierWitness::beforeKill('sink-committed', 'import', $answer);
        }

        return $this->result($answer);
    }

    public function lookup(DataScope $scope, WriteToken $token): TargetResult
    {
        return $this->result($this->call('lookup', $scope, $token));
    }

    private function call(string $action, DataScope $scope, WriteToken $token, ?array $record = null): array
    {
        $command = [PHP_BINARY, __DIR__.'/sink-process.php'];
        $process = proc_open($command, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);

        if (! is_resource($process)) {
            throw new RuntimeException('The dedicated sink process could not start.');
        }
        fwrite($pipes[0], json_encode([
            'action' => $action,
            'account' => $scope->accountId, 'incarnation' => $scope->accountIncarnation, 'workspace' => $scope->workspaceId,
            'fence' => [$scope->deploymentId, $scope->bindingId, $scope->bindingGeneration, $scope->restoreEpoch],
            'key' => $token->operationKey, 'digest' => $token->payloadHash, 'record' => $record,
            'unknown' => getenv('COREX_BULK_MODE') === 'import-unknown',
        ], JSON_THROW_ON_ERROR));
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);

        if ($exit !== 0 || $stderr !== '') {
            throw new RuntimeException('Independent sink process failed ('.$exit.'): '.$stderr);
        }

        return json_decode($stdout, true, flags: JSON_THROW_ON_ERROR);
    }

    private function result(array $answer): TargetResult
    {
        $target = isset($answer['record']) ? new RecordRef(InputIdentity::ENTITY, $answer['record']) : null;

        return new TargetResult($answer['status'], $target, $target === null ? null : 'v1', $answer['receipt'] ?? null, null);
    }
}
