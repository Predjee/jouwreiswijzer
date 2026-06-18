<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\TravelPlan;
use Sulu\Bundle\ContactBundle\Entity\Contact;

final readonly class ChecklistItemToggledEvent
{
    public function __construct(
        public Contact $contact,
        public TravelPlan $travelPlan,
        public string $itemId,
        public bool $checked,
    ) {
    }
}
