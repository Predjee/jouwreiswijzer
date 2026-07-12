<?php

declare(strict_types=1);

namespace App\Controller\Account;

use App\Repository\NotificationRepository;
use App\Repository\TravelPlanRepository;
use App\Account\AccountDashboardBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    use AccountCustomerTrait;

    #[Route('/account', name: 'account', methods: ['GET'])]
    public function index(
        TravelPlanRepository $travelPlanRepository,
        NotificationRepository $notificationRepository,
        AccountDashboardBuilder $dashboardBuilder,
    ): Response {
        [$user, $contact] = $this->getCustomer();
        $travelPlans = $travelPlanRepository->findPublishedByContact($contact);
        $travelPlanSections = $dashboardBuilder->buildSections($travelPlans, $contact);
        $travelPlanCards = \array_merge(
            $travelPlanSections['active'],
            $travelPlanSections['upcoming'],
            $travelPlanSections['unknown'],
            $travelPlanSections['past'],
        );

        return $this->render('account/index.html.twig', [
            'contact' => $contact,
            'email' => $user->getEmail(),
            'travel_plans' => $travelPlanCards,
            'travel_plan_sections' => $travelPlanSections,
            'unread_notification_count' => $notificationRepository->countUnreadForContact($contact),
        ]);
    }
}
