<?php

declare(strict_types=1);

namespace App\Controller\Account;

use App\Dto\SubmitFeedbackRoundRequest;
use App\Dto\SubmitTravelPlanFeedbackRequest;
use App\Entity\TravelPlan;
use App\Entity\TravelPlanFeedback;
use App\Repository\TravelPlanFeedbackRepository;
use App\Repository\TravelPlanRepository;
use App\TravelPlan\Feedback\FeedbackPathResolver;
use App\TravelPlan\Feedback\FeedbackRoundService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class TravelPlanFeedbackController extends AbstractController
{
    use AccountCustomerTrait;
    use TravelPlanFeedbackResponseTrait;

    #[Route('/account/travel-plans/{id}/feedback', name: 'account_travel_plan_feedback', methods: ['POST'])]
    public function feedback(int $id, Request $request, TravelPlanRepository $plans, FeedbackRoundService $feedback): Response
    {
        [, $contact] = $this->getCustomer();
        $travelPlan = $plans->findPublishedForContact($id, $contact);
        if (!$travelPlan instanceof TravelPlan) {
            throw $this->createNotFoundException();
        }

        $submitRequest = new SubmitTravelPlanFeedbackRequest(
            \trim($request->request->getString('message')),
            \trim($request->request->getString('blockPath')) ?: null,
            $request->request->getString('_csrf_token'),
        );
        if (!$this->isCsrfTokenValid('travel_plan_feedback_' . $travelPlan->getId(), $submitRequest->csrfToken)) {
            throw $this->createAccessDeniedException('Ongeldige formulierbeveiliging.');
        }

        $result = $feedback->submitFeedback($travelPlan, $contact, $submitRequest);
        if (!$result->success) {
            return $this->feedbackErrorResponse(
                $request,
                $travelPlan,
                $result->message,
                $result->status,
                $result->feedback,
                $result->blockPath,
                $result->feedbackContext,
                $result->feedbackLabel,
                $result->errorCode,
            );
        }

        if ($request->isXmlHttpRequest() && $result->feedback instanceof TravelPlanFeedback) {
            return $this->feedbackSuccessResponse($travelPlan, $result->feedback, $result);
        }

        $this->addFlash('account_feedback_success', $result->message);

        return $this->redirectToRoute('account_travel_plan', ['id' => $travelPlan->getId()]);
    }

    #[Route('/account/travel-plans/{id}/feedback-round', name: 'account_travel_plan_feedback_round', methods: ['POST'])]
    public function submitFeedbackRound(int $id, Request $request, FeedbackRoundService $feedback, TravelPlanRepository $plans): Response
    {
        [, $contact] = $this->getCustomer();
        $submitRequest = new SubmitFeedbackRoundRequest($id, $request->request->getString('_csrf_token'));
        if (!$feedback->isValidRoundRequest($submitRequest)) {
            throw new BadRequestHttpException('Invalid feedback round request.');
        }
        if (!$this->isCsrfTokenValid('travel_plan_feedback_round_' . $submitRequest->travelPlanId, $submitRequest->csrfToken)) {
            throw $this->createAccessDeniedException('Ongeldige formulierbeveiliging.');
        }

        $travelPlan = $plans->findPublishedForContact($id, $contact);
        if (!$travelPlan instanceof TravelPlan) {
            throw $this->createNotFoundException();
        }

        $result = $feedback->submitRound($travelPlan);
        if (!$result->success) {
            return $this->feedbackErrorResponse($request, $travelPlan, $result->message, $result->status, errorCode: $result->errorCode);
        }

        $this->addFlash(0 === $result->feedbackCount ? 'account_feedback_error' : 'account_feedback_success', $result->message);

        return $this->redirectToRoute('account_travel_plan', ['id' => $submitRequest->travelPlanId, 'mode' => 'feedback']);
    }

    #[Route('/account/travel-plans/{id}/feedback/{feedbackId}/accept', name: 'account_travel_plan_feedback_accept', methods: ['POST'])]
    public function acceptFeedback(
        int $id,
        int $feedbackId,
        Request $request,
        TravelPlanRepository $plans,
        TravelPlanFeedbackRepository $feedbackItems,
        FeedbackRoundService $feedback,
        FeedbackPathResolver $paths,
    ): Response {
        [, $contact] = $this->getCustomer();
        $travelPlan = $plans->findPublishedForContact($id, $contact);
        $feedbackItem = $feedbackItems->find($feedbackId);
        if (
            !$travelPlan instanceof TravelPlan
            || !$feedbackItem instanceof TravelPlanFeedback
            || !$feedback->ownsFeedback($travelPlan, $contact, $feedbackItem)
        ) {
            throw $this->createNotFoundException();
        }
        if (!$this->isCsrfTokenValid('travel_plan_feedback_accept_' . $feedbackItem->getId(), $request->request->getString('_csrf_token'))) {
            throw $this->createAccessDeniedException('Ongeldige formulierbeveiliging.');
        }

        $result = $feedback->acceptFeedback($travelPlan, $contact, $feedbackItem);
        if (null === $result) {
            throw $this->createNotFoundException();
        }
        if (!$result->success) {
            return $this->feedbackErrorResponse($request, $travelPlan, $result->message, $result->status);
        }
        if ($request->isXmlHttpRequest()) {
            return $this->acceptSuccessResponse($travelPlan, $feedbackItem, $result->message, $paths);
        }

        $this->addFlash('account_feedback_success', $result->message);

        return $this->redirectToRoute('account_travel_plan', ['id' => $travelPlan->getId()]);
    }
}
