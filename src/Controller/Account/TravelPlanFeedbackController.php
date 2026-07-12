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
use App\Service\TravelCompanion\CompanionContentHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class TravelPlanFeedbackController extends AbstractController
{
    use AccountCustomerTrait;

    #[Route('/account/travel-plans/{id}/feedback', name: 'account_travel_plan_feedback', methods: ['POST'])]
    public function feedback(
        int $id,
        Request $request,
        TravelPlanRepository $travelPlanRepository,
        TravelPlanFeedbackRepository $feedbackRepository,
        EntityManagerInterface $entityManager,
        FeedbackPathResolver $feedbackPathResolver,
        ValidatorInterface $validator,
    ): Response {
        [, $contact] = $this->getCustomer();
        $travelPlan = $travelPlanRepository->findPublishedForContact($id, $contact);

        if (!$travelPlan instanceof TravelPlan) {
            throw $this->createNotFoundException();
        }

        $submitRequest = new SubmitTravelPlanFeedbackRequest(
            \trim($request->request->getString('message')),
            \trim($request->request->getString('blockPath')) ?: null,
            $request->request->getString('_csrf_token'),
        );

        if (!$this->isCsrfTokenValid(
            'travel_plan_feedback_' . $travelPlan->getId(),
            $submitRequest->csrfToken,
        )) {
            throw $this->createAccessDeniedException('Ongeldige formulierbeveiliging.');
        }

        if (CompanionContentHelper::hasTripStarted($travelPlan->getContent())) {
            return $this->feedbackErrorResponse(
                $request,
                $travelPlan,
                'Deze reis is al begonnen, feedback op het reisplan is niet meer mogelijk. Neem voor wijzigingen tijdens je reis rechtstreeks contact op.',
                Response::HTTP_CONFLICT,
                errorCode: 'trip_started',
            );
        }

        $blockPath = $submitRequest->blockPath;
        $blockType = $feedbackPathResolver->resolveBlockType($travelPlan, $blockPath);
        $feedbackContext = $feedbackPathResolver->context($blockPath);
        $feedbackLabel = $feedbackPathResolver->label($feedbackContext);

        if ($activeFeedback = $feedbackRepository->findActiveForTarget(
            $travelPlan,
            $contact,
            $blockPath,
        )) {
            return $this->feedbackErrorResponse(
                $request,
                $travelPlan,
                'Voor dit onderdeel is al feedback ontvangen en nog in behandeling.',
                Response::HTTP_CONFLICT,
                $activeFeedback,
                $blockPath,
                $feedbackContext,
                $feedbackLabel,
            );
        }

        foreach ($validator->validate($submitRequest) as $violation) {
            if ('message' !== $violation->getPropertyPath()) {
                continue;
            }

            return $this->feedbackErrorResponse(
                $request,
                $travelPlan,
                (string) $violation->getMessage(),
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $feedback = (new TravelPlanFeedback())
            ->setTravelPlan($travelPlan)
            ->setContact($contact)
            ->setBlockPath($blockPath)
            ->setBlockType($blockType)
            ->setMessage($submitRequest->message);

        $travelPlan->setPdfReleasedAt(null);
        $entityManager->persist($feedback);
        $entityManager->flush();

        $successMessage = 'Bedankt, je feedback is ontvangen.';

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'message' => $successMessage,
                'activeFeedbackCount' => \count($feedbackRepository->findActiveForTravelPlan($travelPlan)),
                'html' => $this->renderFeedbackFragment(
                    $travelPlan,
                    $feedback,
                    $blockPath,
                    $feedbackContext,
                    $feedbackLabel,
                ),
            ]);
        }

        $this->addFlash('account_feedback_success', $successMessage);

        return $this->redirectToRoute('account_travel_plan', ['id' => $travelPlan->getId()]);
    }

    #[Route('/account/travel-plans/{id}/feedback-round', name: 'account_travel_plan_feedback_round', methods: ['POST'])]
    public function submitFeedbackRound(
        int $id,
        Request $request,
        ValidatorInterface $validator,
        FeedbackRoundService $feedbackRoundService,
        TravelPlanRepository $travelPlanRepository,
    ): Response {
        [, $contact] = $this->getCustomer();
        $submitRequest = new SubmitFeedbackRoundRequest(
            $id,
            $request->request->getString('_csrf_token'),
        );

        if (\count($validator->validate($submitRequest)) > 0) {
            throw new BadRequestHttpException('Invalid feedback round request.');
        }

        if (!$this->isCsrfTokenValid(
            'travel_plan_feedback_round_'.$submitRequest->travelPlanId,
            $submitRequest->csrfToken,
        )) {
            throw $this->createAccessDeniedException('Ongeldige formulierbeveiliging.');
        }

        $travelPlan = $travelPlanRepository->findPublishedForContact($id, $contact);

        if (!$travelPlan instanceof TravelPlan) {
            throw $this->createNotFoundException();
        }

        if (CompanionContentHelper::hasTripStarted($travelPlan->getContent())) {
            return $this->feedbackErrorResponse(
                $request,
                $travelPlan,
                'Deze reis is al begonnen, feedback op het reisplan is niet meer mogelijk. Neem voor wijzigingen tijdens je reis rechtstreeks contact op.',
                Response::HTTP_CONFLICT,
                errorCode: 'trip_started',
            );
        }

        $feedbackCount = $feedbackRoundService->submit($submitRequest, $contact);

        if (null === $feedbackCount) {
            throw $this->createNotFoundException();
        }

        if (0 === $feedbackCount) {
            $this->addFlash('account_feedback_error', 'Er staan nog geen feedbackpunten klaar om te versturen.');
        } else {
            $this->addFlash(
                'account_feedback_success',
                \sprintf(
                    'Je feedbackronde met %d %s is verstuurd.',
                    $feedbackCount,
                    1 === $feedbackCount ? 'feedbackpunt' : 'feedbackpunten',
                ),
            );
        }

        return $this->redirectToRoute('account_travel_plan', [
            'id' => $submitRequest->travelPlanId,
            'mode' => 'feedback',
        ]);
    }

    #[Route(
        '/account/travel-plans/{id}/feedback/{feedbackId}/accept',
        name: 'account_travel_plan_feedback_accept',
        methods: ['POST'],
    )]
    public function acceptFeedback(
        int $id,
        int $feedbackId,
        Request $request,
        TravelPlanRepository $travelPlanRepository,
        TravelPlanFeedbackRepository $feedbackRepository,
        EntityManagerInterface $entityManager,
        FeedbackPathResolver $feedbackPathResolver,
    ): Response {
        [, $contact] = $this->getCustomer();
        $travelPlan = $travelPlanRepository->findPublishedForContact($id, $contact);
        $feedback = $feedbackRepository->find($feedbackId);

        if (
            !$travelPlan instanceof TravelPlan
            || !$feedback instanceof TravelPlanFeedback
            || $feedback->getTravelPlan()->getId() !== $travelPlan->getId()
            || $feedback->getContact()->getId() !== $contact->getId()
        ) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid(
            'travel_plan_feedback_accept_' . $feedback->getId(),
            $request->request->getString('_csrf_token'),
        )) {
            throw $this->createAccessDeniedException('Ongeldige formulierbeveiliging.');
        }

        if (
            TravelPlanFeedback::STATUS_RESOLVED !== $feedback->getStatus()
            || null !== $feedback->getAcceptedAt()
        ) {
            return $this->feedbackErrorResponse(
                $request,
                $travelPlan,
                'Deze feedback kan niet meer worden bevestigd.',
                Response::HTTP_CONFLICT,
            );
        }

        $feedback->setAcceptedAt(new \DateTimeImmutable());
        $entityManager->flush();

        $successMessage = 'Bedankt, je akkoord is opgeslagen.';

        if ($request->isXmlHttpRequest()) {
            $feedbackContext = $feedbackPathResolver->context($feedback->getBlockPath());

            return new JsonResponse([
                'message' => $successMessage,
                'html' => $this->renderFeedbackFragment(
                    $travelPlan,
                    $feedback,
                    $feedback->getBlockPath(),
                    $feedbackContext,
                    $feedbackPathResolver->label($feedbackContext),
                ),
            ]);
        }

        $this->addFlash('account_feedback_success', $successMessage);

        return $this->redirectToRoute('account_travel_plan', ['id' => $travelPlan->getId()]);
    }

    private function feedbackErrorResponse(
        Request $request,
        TravelPlan $travelPlan,
        string $message,
        int $status,
        ?TravelPlanFeedback $feedback = null,
        ?string $blockPath = null,
        ?string $feedbackContext = null,
        ?string $feedbackLabel = null,
        ?string $errorCode = null,
    ): Response {
        if ($request->isXmlHttpRequest()) {
            $response = ['message' => $message];

            if (null !== $errorCode) {
                $response['code'] = $errorCode;
            }

            if ($feedback instanceof TravelPlanFeedback && null !== $feedbackContext) {
                $response['html'] = $this->renderFeedbackFragment(
                    $travelPlan,
                    $feedback,
                    $blockPath,
                    $feedbackContext,
                    $feedbackLabel ?? $feedbackContext,
                );
            }

            return new JsonResponse($response, $status);
        }

        $this->addFlash('account_feedback_error', $message);

        return $this->redirectToRoute('account_travel_plan', ['id' => $travelPlan->getId()]);
    }

    private function renderFeedbackFragment(
        TravelPlan $travelPlan,
        ?TravelPlanFeedback $feedback,
        ?string $blockPath,
        string $feedbackContext,
        string $feedbackLabel,
    ): string {
        return $this->renderView('account/_travel_plan_feedback_form.html.twig', [
            'travelPlan' => $travelPlan,
            'feedback' => $feedback,
            'blockPath' => $blockPath,
            'feedbackContext' => $feedbackContext,
            'feedbackLabel' => $feedbackLabel,
        ]);
    }
}
