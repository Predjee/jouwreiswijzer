<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\TravelPlan;

final readonly class TravelPlanPublishedEvent
{
    public function __construct(private TravelPlan $travelPlan)
    {
    }

    public function getTravelPlan(): TravelPlan
    {
        return $this->travelPlan;
    }
}
