<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\TravelPlanFeedback;

final readonly class FeedbackIndex
{
    /**
     * @param list<TravelPlanFeedback> $feedbackItems
     *
     * @return array<string, TravelPlanFeedback>
     */
    public function byPath(array $feedbackItems): array
    {
        $feedbackByPath = [];

        foreach ($feedbackItems as $feedback) {
            $key = $feedback->getBlockPath() ?? '';
            $current = $feedbackByPath[$key] ?? null;

            if (
                !$current instanceof TravelPlanFeedback
                || (
                    $this->isActive($feedback)
                    && !$this->isActive($current)
                )
            ) {
                $feedbackByPath[$key] = $feedback;
            }
        }

        return $feedbackByPath;
    }

    /**
     * @param list<TravelPlanFeedback> $feedbackItems
     */
    public function countActive(array $feedbackItems): int
    {
        $count = 0;

        foreach ($feedbackItems as $feedback) {
            if ($this->isActive($feedback)) {
                ++$count;
            }
        }

        return $count;
    }

    public function isActive(TravelPlanFeedback $feedback): bool
    {
        return \in_array($feedback->getStatus(), [
            TravelPlanFeedback::STATUS_OPEN,
            TravelPlanFeedback::STATUS_IN_PROGRESS,
        ], true);
    }
}
