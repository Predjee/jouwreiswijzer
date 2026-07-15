<?php

declare(strict_types=1);

namespace App\Controller\Api\App;

use App\Api\App\Command\PushPreferencesRequest;
use App\Api\App\Command\RegisterPushSubscriptionRequest;
use App\Entity\PushSubscription;
use App\Repository\PushSubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final class PushSubscriptionController extends AbstractController
{
    use ApiAppCustomerTrait;

    #[Route('/api/app/push-subscriptions', name: 'api_app_push_subscriptions_register', methods: ['POST'])]
    public function register(
        #[MapRequestPayload]
        RegisterPushSubscriptionRequest $request,
        PushSubscriptionRepository $pushSubscriptionRepository,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        [, $contact] = $this->getApiCustomer();
        $subscription = $pushSubscriptionRepository->findOneByToken($request->expoPushToken) ?? new PushSubscription();

        $subscription
            ->setContact($contact)
            ->setExpoPushToken($request->expoPushToken)
            ->setPlatform($request->platform)
            ->setAlbumReadyEnabled($request->tripPlanReadyEnabled)
            ->setTripReminderEnabled($request->tripReminderEnabled)
            ->setGeneralEnabled($request->generalEnabled);

        $entityManager->persist($subscription);
        $entityManager->flush();

        return new JsonResponse($this->subscriptionPayload($subscription));
    }

    #[Route('/api/app/push-subscriptions/preferences', name: 'api_app_push_preferences', methods: ['GET'])]
    public function preferences(PushSubscriptionRepository $pushSubscriptionRepository): JsonResponse
    {
        [, $contact] = $this->getApiCustomer();

        return new JsonResponse($this->preferencePayload($pushSubscriptionRepository->findForContact($contact)));
    }

    #[Route('/api/app/push-subscriptions/preferences', name: 'api_app_push_preferences_update', methods: ['PATCH'])]
    public function updatePreferences(
        #[MapRequestPayload]
        PushPreferencesRequest $request,
        PushSubscriptionRepository $pushSubscriptionRepository,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        [, $contact] = $this->getApiCustomer();
        $subscriptions = $pushSubscriptionRepository->findForContact($contact);

        foreach ($subscriptions as $subscription) {
            $subscription
                ->setAlbumReadyEnabled($request->tripPlanReadyEnabled)
                ->setTripReminderEnabled($request->tripReminderEnabled)
                ->setGeneralEnabled($request->generalEnabled);
        }

        $entityManager->flush();

        return new JsonResponse($this->preferencePayload($subscriptions));
    }

    /**
     * @return array<string, mixed>
     */
    private function subscriptionPayload(PushSubscription $subscription): array
    {
        return [
            'id' => $subscription->getId(),
            'expoPushToken' => $subscription->getExpoPushToken(),
            'platform' => $subscription->getPlatform(),
            'preferences' => [
                'trip_reminders' => $subscription->isTripReminderEnabled(),
                'trip_plan_ready' => $subscription->isAlbumReadyEnabled(),
                'general' => $subscription->isGeneralEnabled(),
            ],
        ];
    }

    /**
     * @param list<PushSubscription> $subscriptions
     *
     * @return array<string, mixed>
     */
    private function preferencePayload(array $subscriptions): array
    {
        return [
            'preferences' => [
                'trip_reminders' => $this->isEnabledForAll($subscriptions, 'trip_reminders'),
                'trip_plan_ready' => $this->isEnabledForAll($subscriptions, 'trip_plan_ready'),
                'general' => $this->isEnabledForAll($subscriptions, 'general'),
            ],
            'subscriptionCount' => \count($subscriptions),
        ];
    }

    /**
     * @param list<PushSubscription> $subscriptions
     */
    private function isEnabledForAll(array $subscriptions, string $channel): bool
    {
        foreach ($subscriptions as $subscription) {
            if (!$subscription->isEnabledForChannel($channel)) {
                return false;
            }
        }

        return true;
    }
}
