<?php

declare(strict_types=1);

namespace App\TravelPlan\Feedback;

use App\Entity\TravelPlanFeedback;

final readonly class FeedbackSubmissionResult
{
    public function __construct(
        public bool $success,
        public string $message,
        public int $status,
        public ?TravelPlanFeedback $feedback = null,
        public ?string $blockPath = null,
        public ?string $feedbackContext = null,
        public ?string $feedbackLabel = null,
        public ?string $errorCode = null,
        public ?int $activeFeedbackCount = null,
    ) {
    }
}
