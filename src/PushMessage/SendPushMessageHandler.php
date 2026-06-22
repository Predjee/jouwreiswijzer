<?php

declare(strict_types=1);

namespace App\PushMessage;

use App\Entity\ScheduledPushMessage;
use App\Repository\PushSubscriptionRepository;
use App\Repository\ScheduledPushMessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Verwerkt door de cronjob-gedreven Messenger-consumer (messenger:consume push_messages --time-limit=...).
 * Geen permanente worker. Zie ARCHITECTURE.md sectie 16a.
 */
#[AsMessageHandler]
final readonly class SendPushMessageHandler
{
    public function __construct(
        private ScheduledPushMessageRepository $scheduledPushMessageRepository,
        private PushSubscriptionRepository $pushSubscriptionRepository,
        private ExpoPushClient $expoPushClient,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(SendPushMessage $message): void
    {
        $scheduledMessage = $this->scheduledPushMessageRepository->find($message->scheduledPushMessageId);

        if (!$scheduledMessage instanceof ScheduledPushMessage) {
            $this->logger->warning('ScheduledPushMessage niet gevonden, mogelijk al verwijderd.', [
                'id' => $message->scheduledPushMessageId,
            ]);

            return;
        }

        if (!$scheduledMessage->isQueued()) {
            // Al verwerkt door een eerdere consumer-run; voorkomt dubbel versturen.
            return;
        }

        $contact = $scheduledMessage->getTravelPlan()->getTravelRequest()->getContact();
        $subscriptions = $this->pushSubscriptionRepository->findForContact($contact);
        $eligibleSubscriptions = \array_filter(
            $subscriptions,
            fn ($subscription) => $subscription->isEnabledForChannel($scheduledMessage->getChannel()),
        );

        if ([] === $eligibleSubscriptions) {
            // Geen (toegestane) subscriptions: niets om naar te versturen, maar geen fout.
            $scheduledMessage->markSent();
            $this->entityManager->flush();

            return;
        }

        $lastError = null;

        foreach ($eligibleSubscriptions as $subscription) {
            try {
                $this->expoPushClient->send(
                    $subscription->getExpoPushToken(),
                    $scheduledMessage->getTitle(),
                    $scheduledMessage->getBody(),
                );
            } catch (ExpoPushDeliveryException $exception) {
                $lastError = $exception->getMessage();
                $this->logger->error('Versturen van push-bericht mislukt voor één subscription.', [
                    'scheduledPushMessageId' => $scheduledMessage->getId(),
                    'subscriptionId' => $subscription->getId(),
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        if (null !== $lastError) {
            $scheduledMessage->markFailed($lastError);
        } else {
            $scheduledMessage->markSent();
        }

        $this->entityManager->flush();
    }
}
