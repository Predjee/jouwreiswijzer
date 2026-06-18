<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Notification;
use App\Event\TravelPlanPublishedEvent;
use App\Service\MailNotifier;
use App\Service\NotificationService;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final readonly class TravelPlanPublishedListener implements EventSubscriberInterface
{
    public function __construct(
        private NotificationService $notificationService,
        private MailNotifier $mailNotifier,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TravelPlanPublishedEvent::class => 'onTravelPlanPublished',
        ];
    }

    public function onTravelPlanPublished(TravelPlanPublishedEvent $event): void
    {
        $travelPlan = $event->getTravelPlan();
        $contact = $travelPlan->getTravelRequest()->getContact();
        $url = \sprintf('/account/travel-plans/%d', $travelPlan->getId());

        try {
            $this->notificationService->createForContact(
                $contact,
                Notification::TYPE_TRAVEL_PLAN_PUBLISHED,
                'Je reisplan staat klaar',
                'Je persoonlijke reisplan is gepubliceerd en staat klaar in Mijn Omgeving.',
                $url,
            );
        } catch (\Throwable $exception) {
            $this->logger?->error('Unable to create travel plan published notification.', [
                'exception' => $exception,
                'travelPlanId' => $travelPlan->getId(),
                'contactId' => $contact->getId(),
            ]);
        }

        try {
            $this->mailNotifier->sendTravelPlanPublished($travelPlan);
        } catch (\Throwable $exception) {
            $this->logger?->error('Unable to send travel plan published email.', [
                'exception' => $exception,
                'travelPlanId' => $travelPlan->getId(),
                'contactId' => $contact->getId(),
            ]);
        }
    }
}
