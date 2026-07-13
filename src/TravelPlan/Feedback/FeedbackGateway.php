<?php

declare(strict_types=1);

namespace App\TravelPlan\Feedback;

use App\Entity\TravelPlan;
use App\Entity\TravelPlanFeedback;
use Sulu\Bundle\ContactBundle\Entity\Contact;

interface FeedbackGateway
{
    public function findActiveForTarget(
        TravelPlan $travelPlan,
        Contact $contact,
        ?string $blockPath,
    ): ?TravelPlanFeedback;

    /**
     * @return list<TravelPlanFeedback>
     */
    public function findActiveForTravelPlan(TravelPlan $travelPlan): array;
}
