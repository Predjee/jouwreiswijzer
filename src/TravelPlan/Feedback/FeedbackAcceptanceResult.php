<?php

declare(strict_types=1);

namespace App\TravelPlan\Feedback;

use App\Entity\TravelPlanFeedback;

final readonly class FeedbackAcceptanceResult
{
    public function __construct(
        public bool $success,
        public string $message,
        public int $status,
        public ?TravelPlanFeedback $feedback = null,
    ) {
    }
}
