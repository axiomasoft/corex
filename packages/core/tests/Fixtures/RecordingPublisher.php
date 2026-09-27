<?php

declare(strict_types=1);

namespace CoreX\Testing\Fixtures;

use CoreX\Messaging\Contracts\DurableMessagePublisher;
use CoreX\Messaging\Data\MessageEnvelope;
use RuntimeException;
use Throwable;

final class RecordingPublisher implements DurableMessagePublisher
{
    public const string MODE_SUCCEED = 'succeed';

    public const string MODE_THROW_BEFORE_RECORD = 'before';

    public const string MODE_THROW_AFTER_RECORD = 'after';

    /** @var list<string> */
    public array $recordedMessageIds = [];

    public function __construct(
        public string $mode = self::MODE_SUCCEED,
        private ?Throwable $failure = null,
    ) {}

    public function publish(MessageEnvelope $message): void
    {
        if ($this->mode === self::MODE_THROW_BEFORE_RECORD) {
            throw $this->failure ?? new RuntimeException('publish-failed-before-record');
        }

        $this->recordedMessageIds[] = $message->messageId;

        if ($this->mode === self::MODE_THROW_AFTER_RECORD) {
            throw $this->failure ?? new RuntimeException('publish-failed-after-record');
        }
    }
}
