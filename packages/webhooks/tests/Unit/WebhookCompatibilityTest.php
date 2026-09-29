<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Tests\Unit;

use CoreX\Webhooks\Tests\TestCase;

final class WebhookCompatibilityTest extends TestCase
{
    public function test_planted_webhook_incompatibilities_fail_and_additive_topics_pass(): void
    {
        $source = __DIR__.'/../../../../api/v1';
        $topic = [
            'major' => 'v1',
            'name' => 'orders.placed',
            'payload' => [
                'additionalProperties' => false,
                'properties' => ['id' => ['type' => 'string']],
                'type' => 'object',
            ],
            'pii_class' => 'none',
            'version' => 1,
        ];
        $withTopic = $this->snapshot($source);
        $this->writeWebhooks($withTopic, static function (array $document) use ($topic): array {
            $document['topics'] = [$topic];

            return $document;
        });
        $removed = $this->snapshot($source);
        $semantics = $this->snapshot($withTopic);
        $this->writeWebhooks($semantics, static function (array $document): array {
            $document['rotation']['id_semantics'] = 'event-id';

            return $document;
        });
        $required = $this->snapshot($withTopic);
        $this->writeWebhooks($required, static function (array $document): array {
            $document['topics'][0]['payload']['required'] = ['id'];

            return $document;
        });
        $additive = $this->snapshot($withTopic);
        $this->writeWebhooks($additive, static function (array $document): array {
            $document['topics'][0]['payload']['properties']['note'] = ['type' => 'string'];

            return $document;
        });

        $removal = $this->gate($withTopic, $removed);
        $identity = $this->gate($withTopic, $semantics);
        $input = $this->gate($withTopic, $required);
        $optional = $this->gate($withTopic, $additive);

        self::assertSame(1, $removal['exit']);
        self::assertStringContainsString('topic-removal orders.placed.v1', $removal['output']);
        self::assertSame(1, $identity['exit']);
        self::assertStringContainsString('id-semantics webhooks.json /rotation/id_semantics', $identity['output']);
        self::assertSame(1, $input['exit']);
        self::assertStringContainsString('required-input-addition', $input['output']);
        self::assertSame(0, $optional['exit']);
        self::assertStringContainsString('api-contract: OK', $optional['output']);
    }

    private function snapshot(string $source): string
    {
        $target = sys_get_temp_dir().'/corex-p1420-'.bin2hex(random_bytes(4));
        mkdir($target);

        foreach (scandir($source) ?: [] as $name) {
            if ($name === '.' || $name === '..' || ! is_file($source.'/'.$name)) {
                continue;
            }

            copy($source.'/'.$name, $target.'/'.$name);
        }

        return $target;
    }

    /** @param callable(array<string, mixed>): array<string, mixed> $mutate */
    private function writeWebhooks(string $directory, callable $mutate): void
    {
        $path = $directory.'/webhooks.json';
        $document = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        file_put_contents($path, json_encode($mutate($document), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
    }

    /** @return array{exit: int, output: string} */
    private function gate(string $baseline, string $candidate): array
    {
        $command = [
            PHP_BINARY,
            __DIR__.'/../../../../scripts/check-api-contract.php',
            '--baseline',
            $baseline,
            '--candidate',
            $candidate,
        ];
        $pipes = [];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return ['exit' => proc_close($process), 'output' => $output];
    }
}
