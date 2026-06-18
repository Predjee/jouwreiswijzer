<?php

declare(strict_types=1);

namespace App\Controller\Account;

use App\Entity\TravelPlan;
use App\Entity\TravelPlanChecklistState;
use App\Repository\TravelPlanChecklistStateRepository;
use App\Repository\TravelPlanRepository;
use App\Service\TravelCompanion\TravelCompanionBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Sulu\Bundle\ContactBundle\Entity\Contact;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TravelCompanionController extends AbstractController
{
    use AccountCustomerTrait;

    #[Route('/account/companion/{id}', name: 'account_companion', methods: ['GET'])]
    public function overview(
        int $id,
        TravelPlanRepository $travelPlanRepository,
        TravelCompanionBuilder $companionBuilder,
    ): Response {
        [, $contact] = $this->getCustomer();
        $travelPlan = $this->getPublishedTravelPlan($id, $contact, $travelPlanRepository);

        return $this->render('account/companion.html.twig', [
            'trip' => $companionBuilder->build($travelPlan, $contact),
        ]);
    }

    #[Route('/account/companion/{id}/day/{dayNumber}', name: 'account_companion_day', methods: ['GET'])]
    public function day(
        int $id,
        int $dayNumber,
        TravelPlanRepository $travelPlanRepository,
        TravelCompanionBuilder $companionBuilder,
    ): Response {
        [, $contact] = $this->getCustomer();
        $travelPlan = $this->getPublishedTravelPlan($id, $contact, $travelPlanRepository);

        $trip = $companionBuilder->build($travelPlan, $contact);
        $day = $trip->findDay($dayNumber);

        if (null === $day) {
            throw $this->createNotFoundException();
        }

        return $this->render('account/companion_day.html.twig', [
            'trip' => $trip,
            'day' => $day,
        ]);
    }

    #[Route('/account/companion/{id}/checklist/{itemKey}', name: 'account_companion_checklist_toggle', methods: ['POST'])]
    public function toggleChecklistItem(
        int $id,
        string $itemKey,
        Request $request,
        TravelPlanRepository $travelPlanRepository,
        TravelPlanChecklistStateRepository $checklistStateRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        [, $contact] = $this->getCustomer();
        $travelPlan = $this->getPublishedTravelPlan($id, $contact, $travelPlanRepository);

        if (!$this->isCsrfTokenValid('account_companion_checklist_'.$travelPlan->getId(), $request->request->getString('_csrf_token'))) {
            throw $this->createAccessDeniedException('Ongeldige formulierbeveiliging.');
        }

        if (1 !== \preg_match('/^[a-f0-9]{40}$/D', $itemKey)) {
            throw $this->createNotFoundException();
        }

        $state = $checklistStateRepository->findOneForItem($contact, $travelPlan, $itemKey);

        if (!$state instanceof TravelPlanChecklistState) {
            $state = (new TravelPlanChecklistState())
                ->setContact($contact)
                ->setTravelPlan($travelPlan)
                ->setItemKey($itemKey);
            $entityManager->persist($state);
        }

        $checked = !$state->isChecked();
        $state->setCheckedAt($checked ? new \DateTimeImmutable() : null);
        $entityManager->flush();

        return new JsonResponse(['checked' => $checked]);
    }

    private function getPublishedTravelPlan(
        int $id,
        Contact $contact,
        TravelPlanRepository $travelPlanRepository,
    ): TravelPlan {
        $travelPlan = $travelPlanRepository->findPublishedForContact($id, $contact);

        if (!$travelPlan instanceof TravelPlan) {
            throw $this->createNotFoundException();
        }

        return $travelPlan;
    }
}
