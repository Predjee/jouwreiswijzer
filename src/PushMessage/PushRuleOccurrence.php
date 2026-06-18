<?php

declare(strict_types=1);

namespace App\PushMessage;

final readonly class PushRuleOccurrence
{
    public function __construct(
        public string $sourceKey,
        public \DateTimeImmutable $scheduledFor,
        public string $title,
        public string $body,
        public string $channel,
        public ?string $actionType,
        public ?string $actionTarget,
    ) {
    }
}
