<?php

declare(strict_types=1);

namespace App\Companion;

use App\Entity\TravelPlan;
use Sulu\Bundle\ContactBundle\Entity\Contact;

interface TravelPlanChecklistStateProvider
{
    /**
     * @return array<string, bool>
     */
    public function checkedMapForPlan(Contact $contact, TravelPlan $travelPlan): array;
}
