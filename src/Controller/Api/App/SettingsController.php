<?php

declare(strict_types=1);

namespace App\Controller\Api\App;

use App\Entity\PushSubscription;
use App\TravelPlan\Content\ContentValues;
use App\Entity\TravelPlan;
use App\Repository\PushSubscriptionRepository;
use App\Repository\TravelPlanRepository;
use App\Companion\CompanionContentHelper;
use Sulu\Bundle\ContactBundle\Entity\Contact;
use Sulu\Bundle\SecurityBundle\Entity\User as SuluUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class SettingsController extends AbstractController
{
    use ApiAppCustomerTrait;

    #[Route('/api/app/settings', name: 'api_app_settings', methods: ['GET'])]
    public function __invoke(
        TravelPlanRepository $travelPlanRepository,
        PushSubscriptionRepository $pushSubscriptionRepository,
    ): JsonResponse {
        [$user, $contact] = $this->getApiCustomer();
        $activeTrip = $this->selectActiveTrip($travelPlanRepository->findPublishedByContact($contact));
        $subscriptions = $pushSubscriptionRepository->findForContact($contact);

        return new JsonResponse([
            'profile' => [
                'id' => $contact->getId(),
                'fullName' => $this->resolveFullName($contact),
                'email' => $this->resolveEmail($user, $contact),
                'loggedIn' => true,
            ],
            'activeTrip' => null === $activeTrip ? null : [
                'id' => $activeTrip->getId(),
                'title' => $activeTrip->getTitle(),
            ],
            'push' => [
                'preferences' => [
                    'trip_reminders' => $this->isEnabledForAll($subscriptions, 'trip_reminders'),
                    'trip_plan_ready' => $this->isEnabledForAll($subscriptions, 'trip_plan_ready'),
                    'general' => $this->isEnabledForAll($subscriptions, 'general'),
                ],
                'subscriptionCount' => \count($subscriptions),
                'subscriptions' => \array_map(
                    static fn (PushSubscription $subscription): array => [
                        'id' => $subscription->getId(),
                        'platform' => $subscription->getPlatform(),
                        'createdAt' => $subscription->getCreatedAt()->format(\DateTimeInterface::ATOM),
                    ],
                    $subscriptions,
                ),
            ],
        ]);
    }

    /**
     * @param list<TravelPlan> $travelPlans
     */
    private function selectActiveTrip(array $travelPlans): ?TravelPlan
    {
        $today = new \DateTimeImmutable('today');
        $active = [];
        $upcoming = [];
        $unknown = [];
        $past = [];

        foreach ($travelPlans as $travelPlan) {
            $tripProfile = $this->tripProfile($travelPlan);
            $startDate = CompanionContentHelper::createDate($tripProfile['startDate'] ?? null);
            $endDate = CompanionContentHelper::createDate($tripProfile['endDate'] ?? null);

            if (!$startDate instanceof \DateTimeImmutable || !$endDate instanceof \DateTimeImmutable || $endDate < $startDate) {
                $unknown[] = $travelPlan;
                continue;
            }

            if ($startDate <= $today && $today <= $endDate) {
                $active[] = ['travelPlan' => $travelPlan, 'endDate' => $endDate];
                continue;
            }

            if ($startDate > $today) {
                $upcoming[] = ['travelPlan' => $travelPlan, 'startDate' => $startDate];
                continue;
            }

            $past[] = ['travelPlan' => $travelPlan, 'endDate' => $endDate];
        }

        if ([] !== $active) {
            \usort($active, static fn (array $left, array $right): int => $left['endDate'] <=> $right['endDate']);

            return $active[0]['travelPlan'];
        }

        if ([] !== $upcoming) {
            \usort($upcoming, static fn (array $left, array $right): int => $left['startDate'] <=> $right['startDate']);

            return $upcoming[0]['travelPlan'];
        }

        if ([] !== $unknown) {
            return $unknown[0];
        }

        if ([] !== $past) {
            \usort($past, static fn (array $left, array $right): int => $right['endDate'] <=> $left['endDate']);

            return $past[0]['travelPlan'];
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function tripProfile(TravelPlan $travelPlan): array
    {
        return ContentValues::stringKeyed($travelPlan->getContent()['tripProfile'] ?? null);
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

    private function resolveEmail(SuluUser $user, Contact $contact): string
    {
        $email = $contact->getMainEmail();

        if (\is_string($email) && false !== \filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            return $email;
        }

        return $user->getEmail() ?? '';
    }

    private function resolveFullName(Contact $contact): string
    {
        $fullName = \trim($contact->getFullName());

        return '' !== $fullName ? $fullName : \trim($contact->getFirstName() . ' ' . $contact->getLastName());
    }
}
