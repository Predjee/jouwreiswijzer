<?php

declare(strict_types=1);

namespace App\TravelPlan;

use App\Entity\TravelPlan;
use App\Event\TravelPlanPublishedEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class TravelPlanPublisher
{
    public function __construct(private EventDispatcherInterface $eventDispatcher)
    {
    }

    public function publish(TravelPlan $travelPlan): void
    {
        $wasPublished = TravelPlan::STATUS_PUBLISHED === $travelPlan->getStatus()
            && null !== $travelPlan->getPublishedAt();

        $travelPlan->setStatus(TravelPlan::STATUS_PUBLISHED);

        if (null === $travelPlan->getPublishedAt()) {
            $travelPlan->setPublishedAt(new \DateTimeImmutable());
        }

        if (!$wasPublished) {
            $this->eventDispatcher->dispatch(new TravelPlanPublishedEvent($travelPlan));
        }
    }
}
