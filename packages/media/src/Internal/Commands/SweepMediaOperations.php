<?php

declare(strict_types=1);

namespace CoreX\Media\Internal\Commands;

use CoreX\Media\Internal\Jobs\ConvertMedia;
use CoreX\Media\Internal\Jobs\PurgeMedia;
use CoreX\Media\Internal\MediaOperationStore;
use Illuminate\Console\Command;
use Packman\Attributes\Internal;

#[Internal]
final class SweepMediaOperations extends Command
{
    protected $signature = 'corex:media:sweep {--limit=100 : Maximum due operations to dispatch}';

    protected $description = 'Dispatch due CoreX media operations.';

    public function handle(MediaOperationStore $operations): int
    {
        $limit = (int) $this->option('limit');

        if ($limit < 1 || $limit > 1_000) {
            $this->error('The --limit option must be between 1 and 1000.');

            return self::INVALID;
        }

        $fileIds = $operations->duePurgeFileIds(limit: $limit);
        foreach ($fileIds as $fileId) {
            PurgeMedia::dispatch($fileId);
        }
        $remaining = $limit - count($fileIds);
        $conversionRequests = $remaining > 0 ? $operations->dueConversionRequests(limit: $remaining) : [];
        foreach ($conversionRequests as $request) {
            ConvertMedia::dispatch(fileId: $request->fileId, profileKey: $request->profileKey, operationKey: $request->operationKey);
        }
        $this->info('Dispatched '.(count($fileIds) + count($conversionRequests)).' media operation(s).');

        return self::SUCCESS;
    }
}
