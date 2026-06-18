<?php

declare(strict_types=1);

namespace App\PushMessage;

/**
 * Messenger-bericht: verstuur één ScheduledPushMessage.
 * Verwerkt door de cronjob-gedreven consumer, zie ARCHITECTURE.md sectie 16a.
 */
final readonly class SendPushMessage
{
    public function __construct(
        public int $scheduledPushMessageId,
    ) {
    }
}
