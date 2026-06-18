<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class SubmitFeedbackRoundRequest
{
    public function __construct(
        #[Assert\Positive]
        public int $travelPlanId,
        #[Assert\NotBlank]
        public string $csrfToken,
    ) {
    }
}
