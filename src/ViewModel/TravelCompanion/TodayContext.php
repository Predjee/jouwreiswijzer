<?php

declare(strict_types=1);

namespace App\ViewModel\TravelCompanion;

final readonly class TodayContext
{
    public function __construct(
        public ?TodayTravelPlan $travelPlan,
        public bool $hasTravelPlan,
    )
    {
    }
}
