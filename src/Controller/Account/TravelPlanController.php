<?php

declare(strict_types=1);

namespace App\Controller\Account;

use App\Entity\TravelPlan;
use App\Repository\TravelPlanFeedbackRepository;
use App\Repository\TravelPlanRepository;
use App\Service\FeedbackIndex;
use App\TravelPlan\Pdf\TravelPlanPdfGenerator;
use App\TravelPlan\Pdf\TravelPlanPdfStorage;
use App\TravelPlan\Renderer\TravelPlanRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

final class TravelPlanController extends AbstractController
{
    use AccountCustomerTrait;

    #[Route('/account/travel-plans/{id}', name: 'account_travel_plan', methods: ['GET'])]
    public function travelPlan(
        int $id,
        TravelPlanRepository $travelPlanRepository,
        TravelPlanFeedbackRepository $feedbackRepository,
        TravelPlanRenderer $renderer,
        FeedbackIndex $feedbackIndex,
    ): Response {
        [, $contact] = $this->getCustomer();
        $travelPlan = $travelPlanRepository->findPublishedForContact($id, $contact);

        if (!$travelPlan instanceof TravelPlan) {
            throw $this->createNotFoundException();
        }

        $feedbackItems = $feedbackRepository->findForPlanAndContact($travelPlan, $contact);
        $feedbackByPath = $feedbackIndex->byPath($feedbackItems);

        return $this->render('account/travel_plan.html.twig', [
            'travel_plan' => $travelPlan,
            'travel_plan_feedback' => $feedbackByPath[''] ?? null,
            'feedback_round_count' => $feedbackIndex->countActive($feedbackItems),
            'travel_plan_view_html' => $renderer->renderForAccount($travelPlan, [], false),
            'travel_plan_feedback_html' => $renderer->renderForAccount($travelPlan, $feedbackByPath),
        ]);
    }

    #[Route('/account/travel-plans/{id}/pdf', name: 'account_travel_plan_pdf', methods: ['GET'])]
    public function downloadPdf(
        int $id,
        TravelPlanRepository $travelPlanRepository,
        TravelPlanFeedbackRepository $feedbackRepository,
        TravelPlanPdfGenerator $pdfGenerator,
        TravelPlanPdfStorage $pdfStorage,
    ): Response {
        [, $contact] = $this->getCustomer();
        $travelPlan = $travelPlanRepository->findPublishedForContact($id, $contact);

        if (
            !$travelPlan instanceof TravelPlan
            || !$travelPlan->isPdfReleased()
            || [] !== $feedbackRepository->findBlockingForPdfRelease($travelPlan)
        ) {
            throw $this->createNotFoundException();
        }

        $response = new Response($pdfGenerator->generate($travelPlan));
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set(
            'Content-Disposition',
            HeaderUtils::makeDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                $pdfStorage->createFilename($travelPlan),
            ),
        );

        return $response;
    }
}
