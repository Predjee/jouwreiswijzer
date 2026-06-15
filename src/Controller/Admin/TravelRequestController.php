<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Admin\TravelRequestAdmin;
use App\Entity\TravelPlan;
use App\Entity\TravelPlanFeedback;
use App\Entity\TravelRequest;
use App\Repository\TravelPlanFeedbackRepository;
use App\Repository\TravelRequestRepository;
use App\Service\TravelPlanContentFactory;
use App\TravelPlan\Pdf\TravelPlanPdfStorage;
use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\View\ViewHandlerInterface;
use Sulu\Component\Rest\AbstractRestController;
use Sulu\Component\Rest\ListBuilder\Doctrine\DoctrineListBuilderFactoryInterface;
use Sulu\Component\Rest\ListBuilder\Metadata\FieldDescriptorFactoryInterface;
use Sulu\Component\Rest\ListBuilder\PaginatedRepresentation;
use Sulu\Component\Rest\RestHelperInterface;
use Sulu\Component\Security\SecuredControllerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class TravelRequestController extends AbstractRestController implements SecuredControllerInterface
{
    public function __construct(
        ViewHandlerInterface $viewHandler,
        private readonly RestHelperInterface $restHelper,
        private readonly FieldDescriptorFactoryInterface $fieldDescriptorFactory,
        private readonly DoctrineListBuilderFactoryInterface $listBuilderFactory,
        private readonly TravelRequestRepository $repository,
        private readonly EntityManagerInterface $entityManager,
        private readonly TravelPlanContentFactory $contentFactory,
        private readonly TravelPlanPdfStorage $pdfStorage,
        private readonly TravelPlanFeedbackRepository $feedbackRepository,
    ) {
        parent::__construct($viewHandler);
    }

    public function cgetAction(): Response
    {
        $fieldDescriptors = $this->fieldDescriptorFactory->getFieldDescriptors(
            TravelRequestAdmin::LIST_KEY,
        );
        $listBuilder = $this->listBuilderFactory->create(TravelRequest::class);
        $this->restHelper->initializeListBuilder($listBuilder, $fieldDescriptors);

        $list = new PaginatedRepresentation(
            $listBuilder->execute(),
            TravelRequestAdmin::RESOURCE_KEY,
            (int) $listBuilder->getCurrentPage(),
            (int) $listBuilder->getLimit(),
            (int) $listBuilder->count(),
        );

        return $this->handleView($this->view($list));
    }

    public function getAction(int $id): Response
    {
        $travelRequest = $this->findTravelRequest($id);

        return $this->handleView($this->view($this->serialize($travelRequest)));
    }

    public function putAction(Request $request, int $id): Response
    {
        $travelRequest = $this->findTravelRequest($id);
        $data = $request->getPayload();
        $status = $data->getString('status');

        if (!\in_array($status, self::statuses(), true)) {
            throw new BadRequestHttpException('Invalid travel request status.');
        }

        $internalNotes = $data->get('internalNotes');

        if (null !== $internalNotes && !\is_string($internalNotes)) {
            throw new BadRequestHttpException('Internal notes must be a string.');
        }

        $travelRequest
            ->setStatus($status)
            ->setInternalNotes(null === $internalNotes ? null : \trim($internalNotes));

        $this->entityManager->flush();

        return $this->handleView($this->view($this->serialize($travelRequest)));
    }

    public function getPlanAction(int $id): Response
    {
        $travelPlan = $this->getOrCreateTravelPlan($this->findTravelRequest($id));

        return $this->handleView($this->view($this->serializeTravelPlan($travelPlan)));
    }

    public function putPlanAction(Request $request, int $id): Response
    {
        $travelPlan = $this->getOrCreateTravelPlan($this->findTravelRequest($id));
        $data = $request->getPayload();
        $title = \trim($data->getString('title'));
        $status = $data->getString('status');
        $formData = $data->all();

        if ('' === $title) {
            throw new BadRequestHttpException('Title is required.');
        }

        if (!\in_array($status, [TravelPlan::STATUS_DRAFT, TravelPlan::STATUS_PUBLISHED], true)) {
            throw new BadRequestHttpException('Invalid travel plan status.');
        }

        $travelPlan
            ->setTitle($title)
            ->setStatus($status)
            ->setContent($this->contentFactory->fromFormData($formData, $travelPlan->getContent()));

        if (TravelPlan::STATUS_PUBLISHED === $status && null === $travelPlan->getPublishedAt()) {
            $travelPlan->setPublishedAt(new \DateTimeImmutable());
        } elseif (TravelPlan::STATUS_DRAFT === $status) {
            $travelPlan->setPublishedAt(null);
        }

        if (TravelPlan::STATUS_PUBLISHED === $status) {
            $this->pdfStorage->generateAndStore($travelPlan);
        } else {
            $this->entityManager->flush();
        }

        return $this->handleView($this->view($this->serializeTravelPlan($travelPlan)));
    }

    public function getSecurityContext(): string
    {
        return TravelRequestAdmin::SECURITY_CONTEXT;
    }

    public function getLocale(Request $request): string
    {
        $locale = $request->query->get('locale');
        if (\is_string($locale)) {
            return $locale;
        }

        return $request->getLocale();
    }

    private function findTravelRequest(int $id): TravelRequest
    {
        $travelRequest = $this->repository->find($id);

        if (!$travelRequest instanceof TravelRequest) {
            throw new NotFoundHttpException(\sprintf('TravelRequest "%d" was not found.', $id));
        }

        return $travelRequest;
    }

    private function getOrCreateTravelPlan(TravelRequest $travelRequest): TravelPlan
    {
        $travelPlan = $this->entityManager
            ->getRepository(TravelPlan::class)
            ->findOneBy(['travelRequest' => $travelRequest]);

        if ($travelPlan instanceof TravelPlan) {
            return $travelPlan;
        }

        $contactName = \trim($travelRequest->getContact()->getFullName());
        $title = '' === $contactName
            ? \sprintf('Reisplan aanvraag %d', $travelRequest->getId())
            : \sprintf('Reisplan %s', $contactName);

        $travelPlan = (new TravelPlan())
            ->setTravelRequest($travelRequest)
            ->setTitle($title)
            ->setStatus(TravelPlan::STATUS_DRAFT)
            ->setContent($this->contentFactory->createDefault());

        $travelRequest->setStatus(TravelRequest::STATUS_PLAN_IN_PROGRESS);
        $this->entityManager->persist($travelPlan);
        $this->entityManager->flush();

        return $travelPlan;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeTravelPlan(TravelPlan $travelPlan): array
    {
        $data = \array_merge([
            'id' => $travelPlan->getTravelRequest()->getId(),
            'travelPlanId' => $travelPlan->getId(),
            'title' => $travelPlan->getTitle(),
            'status' => $travelPlan->getStatus(),
            'pdfMediaId' => $travelPlan->getPdfMediaId(),
            'pdfGeneratedAt' => $travelPlan->getPdfGeneratedAt()?->format('d-m-Y H:i'),
            'customerVisible' => $travelPlan->isVisibleForCustomer(),
        ], $this->contentFactory->toFormData($travelPlan->getContent()));

        $activeFeedback = $this->feedbackRepository->findActiveForTravelPlan($travelPlan);
        $data['feedbackSummary'] = \array_map(
            fn (TravelPlanFeedback $feedback): array => [
                'id' => $feedback->getId(),
                'label' => $this->feedbackTargetLabel($data, $feedback),
                'status' => $feedback->getStatus(),
                'anchorId' => 'travel-plan-feedback-' . $feedback->getId(),
                'blockPath' => $feedback->getBlockPath(),
                'blockType' => $feedback->getBlockType(),
            ],
            $activeFeedback,
        );

        foreach ($activeFeedback as $feedback) {
            $this->attachFeedback($data, $feedback);
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function attachFeedback(array &$data, TravelPlanFeedback $feedback): void
    {
        $blockPath = $feedback->getBlockPath();
        $serialized = [
            'id' => $feedback->getId(),
            'status' => $feedback->getStatus(),
            'message' => $feedback->getMessage(),
            'blockPath' => $blockPath,
            'blockType' => $feedback->getBlockType(),
            'contactName' => $feedback->getContact()->getFullName(),
            'createdAt' => $feedback->getCreatedAt()->format('d-m-Y H:i'),
        ];

        if (null === $blockPath) {
            $data['planFeedback'] ??= $serialized;

            return;
        }

        if (1 === \preg_match('/^sections\[(\d+)]$/D', $blockPath, $matches)) {
            $sectionIndex = (int) $matches[1];

            if (isset($data['sections'][$sectionIndex]) && \is_array($data['sections'][$sectionIndex])) {
                $data['sections'][$sectionIndex]['_feedback'] ??= $serialized;
            }

            return;
        }

        if (1 !== \preg_match(
            '/^sections\[(\d+)]\.blocks\[(\d+)]$/D',
            $blockPath,
            $matches,
        )) {
            return;
        }

        $sectionIndex = (int) $matches[1];
        $blockIndex = (int) $matches[2];

        if (
            isset($data['sections'][$sectionIndex]['blocks'][$blockIndex])
            && \is_array($data['sections'][$sectionIndex]['blocks'][$blockIndex])
        ) {
            $data['sections'][$sectionIndex]['blocks'][$blockIndex]['_feedback'] ??= $serialized;
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function feedbackTargetLabel(array $data, TravelPlanFeedback $feedback): string
    {
        $blockPath = $feedback->getBlockPath();

        if (null === $blockPath) {
            return 'Hele reisplan';
        }

        if (1 === \preg_match('/^sections\[(\d+)]$/D', $blockPath, $matches)) {
            $sectionIndex = (int) $matches[1];
            $section = $data['sections'][$sectionIndex] ?? [];
            $title = \trim((string) ($section['title'] ?? ''));

            return \sprintf(
                'Sectie %d: %s',
                $sectionIndex + 1,
                '' !== $title ? $title : ($feedback->getBlockType() ?? 'Onderdeel'),
            );
        }

        if (1 === \preg_match(
            '/^sections\[(\d+)]\.blocks\[(\d+)]$/D',
            $blockPath,
            $matches,
        )) {
            $sectionIndex = (int) $matches[1];
            $blockIndex = (int) $matches[2];
            $section = $data['sections'][$sectionIndex] ?? [];
            $block = $section['blocks'][$blockIndex] ?? [];
            $dayNumber = \max(1, (int) ($section['dayNumber'] ?? $sectionIndex + 1));
            $title = \trim((string) ($block['title'] ?? ''));

            return \sprintf(
                'Dag %d: %s',
                $dayNumber,
                '' !== $title ? $title : ($feedback->getBlockType() ?? 'Dagonderdeel'),
            );
        }

        return $feedback->getBlockType() ?? 'Reisplanonderdeel';
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(TravelRequest $travelRequest): array
    {
        $contact = $travelRequest->getContact();
        $travelPlan = $this->entityManager
            ->getRepository(TravelPlan::class)
            ->findOneBy(['travelRequest' => $travelRequest]);

        return [
            'id' => $travelRequest->getId(),
            'status' => $travelRequest->getStatus(),
            'internalNotes' => $travelRequest->getInternalNotes(),
            'summary' => $travelRequest->getSummary(),
            'createdAt' => $travelRequest->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $travelRequest->getUpdatedAt()->format(\DateTimeInterface::ATOM),
            'contactName' => $contact->getFullName(),
            'contactEmail' => $contact->getMainEmail(),
            'contactPhone' => $contact->getMainPhone(),
            'travelPlanStatus' => $travelPlan instanceof TravelPlan
                ? \sprintf('Conceptreisplan #%d is aangemaakt.', $travelPlan->getId())
                : 'Nog geen reisplan aangemaakt.',
        ];
    }

    /**
     * @return string[]
     */
    private static function statuses(): array
    {
        return [
            TravelRequest::STATUS_NEW,
            TravelRequest::STATUS_IN_PROGRESS,
            TravelRequest::STATUS_NEEDS_INFO,
            TravelRequest::STATUS_PLAN_IN_PROGRESS,
            TravelRequest::STATUS_PROPOSAL_READY,
            TravelRequest::STATUS_COMPLETED,
            TravelRequest::STATUS_CANCELLED,
        ];
    }
}
