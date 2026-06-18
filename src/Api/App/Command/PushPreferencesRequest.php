<?php

declare(strict_types=1);

namespace App\Api\App\Command;

use Symfony\Component\Validator\Constraints as Assert;

final class PushPreferencesRequest
{
    public function __construct(
        #[Assert\Type('bool')]
        public bool $tripPlanReadyEnabled = true,
        #[Assert\Type('bool')]
        public bool $tripReminderEnabled = true,
        #[Assert\Type('bool')]
        public bool $generalEnabled = true,
    ) {
    }
}
