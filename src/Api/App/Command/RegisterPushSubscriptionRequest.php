<?php

declare(strict_types=1);

namespace App\Api\App\Command;

use Symfony\Component\Validator\Constraints as Assert;

final class RegisterPushSubscriptionRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public string $expoPushToken,
        #[Assert\Choice(choices: ['ios', 'android'])]
        public string $platform,
        #[Assert\Type('bool')]
        public bool $tripPlanReadyEnabled = true,
        #[Assert\Type('bool')]
        public bool $tripReminderEnabled = true,
        #[Assert\Type('bool')]
        public bool $generalEnabled = true,
    ) {
    }
}
