<?php

declare(strict_types=1);

namespace App\Controller\Api\App;

use App\Api\App\Mapper\ReminderMapper;
use App\Repository\TravelPlanRepository;
use App\Service\TravelCompanion\ReminderPlanBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class ReminderController extends AbstractController
{
    use ApiAppCustomerTrait;

    #[Route('/api/app/trips/{id}/reminders', name: 'api_app_trip_reminders', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function list(
        int $id,
        TravelPlanRepository $travelPlanRepository,
        ReminderPlanBuilder $reminderPlanBuilder,
        ReminderMapper $mapper,
    ): JsonResponse {
        [, $contact] = $this->getApiCustomer();
        $travelPlan = $travelPlanRepository->findPublishedForContact($id, $contact);

        if (null === $travelPlan) {
            throw new NotFoundHttpException();
        }

        $from = new \DateTimeImmutable('today');
        $to = $from->modify('+2 days');

        return new JsonResponse($mapper->map(
            $travelPlan->getId() ?? $id,
            $reminderPlanBuilder->buildForRange($travelPlan, $from, $to),
        ));
    }
}
