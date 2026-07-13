<?php

declare(strict_types=1);

namespace App\TravelPlan\Feedback;

final readonly class FeedbackRoundSubmissionResult
{
    public function __construct(
        public bool $success,
        public string $message,
        public int $status,
        public int $feedbackCount = 0,
        public ?string $errorCode = null,
    ) {
    }
}
