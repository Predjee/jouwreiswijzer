<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\TravelPlan;
use App\Entity\TravelPlanFeedback;

final readonly class FeedbackRoundSubmittedEvent
{
    /**
     * @param list<TravelPlanFeedback> $feedbackItems
     */
    public function __construct(
        private TravelPlan $travelPlan,
        private array $feedbackItems,
    ) {
    }

    public function getTravelPlan(): TravelPlan
    {
        return $this->travelPlan;
    }

    /**
     * @return list<TravelPlanFeedback>
     */
    public function getFeedbackItems(): array
    {
        return $this->feedbackItems;
    }

    public function getFeedbackCount(): int
    {
        return \count($this->feedbackItems);
    }
}
