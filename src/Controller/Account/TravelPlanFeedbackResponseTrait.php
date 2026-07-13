<?php

declare(strict_types=1);

namespace App\Controller\Account;

use App\Entity\TravelPlan;
use App\Entity\TravelPlanFeedback;
use App\TravelPlan\Feedback\FeedbackPathResolver;
use App\TravelPlan\Feedback\FeedbackSubmissionResult;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

trait TravelPlanFeedbackResponseTrait
{
    private function feedbackErrorResponse(
        Request $request,
        TravelPlan $travelPlan,
        string $message,
        int $status,
        ?TravelPlanFeedback $feedback = null,
        ?string $blockPath = null,
        ?string $context = null,
        ?string $label = null,
        ?string $errorCode = null,
    ): Response {
        if (!$request->isXmlHttpRequest()) {
            $this->addFlash('account_feedback_error', $message);

            return $this->redirectToRoute('account_travel_plan', ['id' => $travelPlan->getId()]);
        }

        $response = ['message' => $message];
        if (null !== $errorCode) {
            $response['code'] = $errorCode;
        }
        if ($feedback instanceof TravelPlanFeedback && null !== $context) {
            $response['html'] = $this->renderFeedbackFragment($travelPlan, $feedback, $blockPath, $context, $label ?? $context);
        }

        return new JsonResponse($response, $status);
    }

    private function feedbackSuccessResponse(
        TravelPlan $travelPlan,
        TravelPlanFeedback $feedback,
        FeedbackSubmissionResult $result,
    ): JsonResponse {
        return new JsonResponse([
            'message' => $result->message,
            'activeFeedbackCount' => $result->activeFeedbackCount ?? 0,
            'html' => $this->renderFeedbackFragment(
                $travelPlan,
                $feedback,
                $result->blockPath,
                $result->feedbackContext ?? 'plan',
                $result->feedbackLabel ?? 'Feedback op dit reisplan',
            ),
        ]);
    }

    private function acceptSuccessResponse(
        TravelPlan $travelPlan,
        TravelPlanFeedback $feedback,
        string $message,
        FeedbackPathResolver $paths,
    ): JsonResponse {
        $context = $paths->context($feedback->getBlockPath());

        return new JsonResponse([
            'message' => $message,
            'html' => $this->renderFeedbackFragment($travelPlan, $feedback, $feedback->getBlockPath(), $context, $paths->label($context)),
        ]);
    }

    private function renderFeedbackFragment(
        TravelPlan $travelPlan,
        ?TravelPlanFeedback $feedback,
        ?string $blockPath,
        string $context,
        string $label,
    ): string {
        return $this->renderView('account/_travel_plan_feedback_form.html.twig', [
            'travelPlan' => $travelPlan,
            'feedback' => $feedback,
            'blockPath' => $blockPath,
            'feedbackContext' => $context,
            'feedbackLabel' => $label,
        ]);
    }
}
