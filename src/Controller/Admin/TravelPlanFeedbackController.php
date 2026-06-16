<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Admin\TravelRequestAdmin;
use App\Entity\TravelPlan;
use App\Entity\TravelPlanFeedback;
use App\Repository\TravelPlanRepository;
use App\Repository\TravelPlanFeedbackRepository;
use App\Repository\TravelRequestRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\View\ViewHandlerInterface;
use Sulu\Component\Rest\AbstractRestController;
use Sulu\Component\Security\SecuredControllerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class TravelPlanFeedbackController extends AbstractRestController implements SecuredControllerInterface
{
    public function __construct(
        ViewHandlerInterface $viewHandler,
        private readonly TravelPlanFeedbackRepository $repository,
        private readonly TravelPlanRepository $travelPlanRepository,
        private readonly TravelRequestRepository $travelRequestRepository,
        private readonly NotificationService $notificationService,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct($viewHandler);
    }

    public function putStatusAction(Request $request, int $id): Response
    {
        $feedback = $this->repository->find($id);

        if (!$feedback instanceof TravelPlanFeedback) {
            throw new NotFoundHttpException(\sprintf('TravelPlanFeedback "%d" was not found.', $id));
        }

        $data = $request->getPayload();
        $status = $data->getString('status');

        if (!\in_array($status, [
            TravelPlanFeedback::STATUS_IN_PROGRESS,
            TravelPlanFeedback::STATUS_RESOLVED,
        ], true)) {
            throw new BadRequestHttpException('Invalid feedback status.');
        }

        if (!\in_array($feedback->getStatus(), [
            TravelPlanFeedback::STATUS_OPEN,
            TravelPlanFeedback::STATUS_IN_PROGRESS,
        ], true)) {
            throw new BadRequestHttpException('Only active feedback can be updated.');
        }

        if (TravelPlanFeedback::STATUS_IN_PROGRESS === $status) {
            $feedback->setStatus(TravelPlanFeedback::STATUS_IN_PROGRESS);
        } else {
            $note = \trim($data->getString('adminResolutionNote'));
            $snapshot = $data->all('resolvedContentSnapshot');

            if ([] === $snapshot) {
                $snapshot = $this->resolveStoredContentSnapshot($feedback);
            }

            if (
                null !== $feedback->getBlockType()
                && ($snapshot['type'] ?? null) !== $feedback->getBlockType()
            ) {
                throw new BadRequestHttpException('The content snapshot does not match the feedback target.');
            }

            unset($snapshot['_feedback']);

            $feedback
                ->setStatus(TravelPlanFeedback::STATUS_RESOLVED)
                ->setAdminResolutionNote('' === $note ? null : $note)
                ->setResolvedContentSnapshot($snapshot)
                ->setResolvedAt(new \DateTimeImmutable())
                ->setAcceptedAt(null);
        }

        $this->entityManager->flush();

        return $this->handleView($this->view($this->serialize($feedback)));
    }

    public function notifyProcessedForRequest(int $id): JsonResponse
    {
        $travelRequest = $this->travelRequestRepository->find($id);

        if (null === $travelRequest) {
            throw new NotFoundHttpException(\sprintf('TravelRequest "%d" was not found.', $id));
        }

        $travelPlan = $this->travelPlanRepository->findOneBy(['travelRequest' => $travelRequest]);

        if (!$travelPlan instanceof TravelPlan) {
            throw new NotFoundHttpException(\sprintf(
                'No TravelPlan was found for TravelRequest "%d".',
                $id,
            ));
        }

        $this->notificationService->notifyFeedbackProcessed($travelPlan);

        return new JsonResponse([
            'id' => $id,
            'travelPlanId' => $travelPlan->getId(),
            'message' => 'De klant is geïnformeerd dat feedback is verwerkt.',
        ]);
    }

    public function getSecurityContext(): string
    {
        return TravelRequestAdmin::SECURITY_CONTEXT;
    }

    public function getLocale(Request $request): string
    {
        return $request->getLocale();
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveStoredContentSnapshot(TravelPlanFeedback $feedback): array
    {
        $content = $feedback->getTravelPlan()->getContent();
        $blockPath = $feedback->getBlockPath();

        if (null === $blockPath) {
            return $content;
        }

        if (1 === \preg_match('/^sections\[(\d+)]$/D', $blockPath, $matches)) {
            $snapshot = $content['sections'][(int) $matches[1]] ?? null;
        } elseif (1 === \preg_match(
            '/^sections\[(\d+)]\.blocks\[(\d+)]$/D',
            $blockPath,
            $matches,
        )) {
            $snapshot = $content['sections'][(int) $matches[1]]['blocks'][(int) $matches[2]] ?? null;
        } else {
            $snapshot = null;
        }

        if (!\is_array($snapshot)) {
            throw new BadRequestHttpException('The feedback target no longer exists.');
        }

        return $snapshot;
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(TravelPlanFeedback $feedback): array
    {
        return [
            'id' => $feedback->getId(),
            'status' => $feedback->getStatus(),
            'message' => $feedback->getMessage(),
            'blockPath' => $feedback->getBlockPath(),
            'blockType' => $feedback->getBlockType(),
            'adminResolutionNote' => $feedback->getAdminResolutionNote(),
        ];
    }
}
