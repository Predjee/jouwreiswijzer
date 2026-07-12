<?php

declare(strict_types=1);

namespace App\Service\TravelCompanion;

use App\Entity\TravelPlan;
use Sulu\Bundle\ContactBundle\Entity\Contact;

interface TravelPlanChecklistStateProvider
{
    /**
     * @return array<string, bool>
     */
    public function checkedMapForPlan(Contact $contact, TravelPlan $travelPlan): array;
}
