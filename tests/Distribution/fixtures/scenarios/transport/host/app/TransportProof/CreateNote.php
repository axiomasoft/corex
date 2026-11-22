<?php

declare(strict_types=1);

namespace App\TransportProof;

use CoreX\Foundation\Laravel\ReferenceWrites;
use CoreX\Invocation\Contracts\OperationHandler;
use CoreX\Invocation\Contracts\RequestContext;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;
use RuntimeException;

final readonly class CreateNote implements OperationHandler
{
    public function __construct(private DatabaseManager $database) {}

    public function handle(RequestContext $context, array $input, array $parameters): array
    {
        $connection = $this->database->connection();

        if ($connection->transactionLevel() < 1) {
            throw new RuntimeException('The installed mutation requires Operations owner transaction admission.');
        }

        $scope = ReferenceWrites::current();
        ReferenceWrites::enlist($connection, $scope, NotesManifest::OWNER, 'note-create', ['subject' => $input['resource_id']]);
        $record = $connection->table('installed_notes_subjects')->where([
            'workspace_id' => $scope->workspaceId, 'id' => $input['resource_id'],
        ])->sole();
        $note = [
            'id' => (string) Str::uuid7(),
            'resource_id' => $input['resource_id'],
            'title' => $input['title'],
            'private_label' => $record->private_label,
        ];
        $connection->table('installed_notes')->insert([
            ...$note, 'principal_id' => $scope->principalId, 'workspace_id' => $scope->workspaceId,
        ]);

        return $note;
    }
}
