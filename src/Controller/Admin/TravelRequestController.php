<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Admin\TravelRequestAdmin;
use App\Entity\TravelPlan;
use App\Entity\TravelPlanFeedback;
use App\Entity\TravelRequest;
use App\Repository\TravelPlanFeedbackRepository;
use App\Repository\TravelRequestRepository;
use App\Service\TravelPlanPublisher;
use App\Service\TravelPlanContentFactory;
use App\TravelPlan\Pdf\TravelPlanPdfStorage;
use App\TravelPlan\BlockPath;
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
        private readonly TravelPlanPublisher $travelPlanPublisher,
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

        try {
            $content = $this->contentFactory->fromFormData($formData, $travelPlan->getContent());
        } catch (\InvalidArgumentException $exception) {
            throw new BadRequestHttpException($exception->getMessage(), $exception);
        }

        $travelPlan
            ->setTitle($title)
            ->setContent($content);

        if (TravelPlan::STATUS_PUBLISHED === $status) {
            $this->travelPlanPublisher->publish($travelPlan);
        } else {
            $travelPlan
                ->setStatus(TravelPlan::STATUS_DRAFT)
                ->setPublishedAt(null);
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
            'pdfReleasedAt' => $travelPlan->getPdfReleasedAt()?->format('d-m-Y H:i'),
            'customerVisible' => $travelPlan->isVisibleForCustomer(),
        ], $this->contentFactory->toFormData($travelPlan->getContent()));

        $activeFeedback = $this->feedbackRepository->findActiveForTravelPlan($travelPlan);
        $blockingFeedback = $this->feedbackRepository->findBlockingForPdfRelease($travelPlan);
        $data['pdfReleaseReady'] = TravelPlan::STATUS_PUBLISHED === $travelPlan->getStatus()
            && null === $travelPlan->getPdfReleasedAt()
            && [] === $blockingFeedback;
        $data['pdfReleaseStatus'] = $this->pdfReleaseStatus($travelPlan, $blockingFeedback);
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
     * @param list<TravelPlanFeedback> $blockingFeedback
     */
    private function pdfReleaseStatus(TravelPlan $travelPlan, array $blockingFeedback): string
    {
        if (null !== $travelPlan->getPdfReleasedAt()) {
            return 'PDF vrijgegeven op '.$travelPlan->getPdfReleasedAt()->format('d-m-Y H:i');
        }

        if (TravelPlan::STATUS_PUBLISHED !== $travelPlan->getStatus()) {
            return 'Publiceer het reisplan voordat de PDF kan worden vrijgegeven.';
        }

        if ([] === $blockingFeedback) {
            return 'Alle feedback is afgerond en geaccepteerd. De PDF kan worden vrijgegeven.';
        }

        $activeCount = 0;
        $awaitingAcceptanceCount = 0;

        foreach ($blockingFeedback as $feedback) {
            if (TravelPlanFeedback::STATUS_RESOLVED === $feedback->getStatus()) {
                ++$awaitingAcceptanceCount;
            } else {
                ++$activeCount;
            }
        }

        return \sprintf(
            'Nog niet vrij te geven: %d open/in behandeling, %d wacht op klantacceptatie.',
            $activeCount,
            $awaitingAcceptanceCount,
        );
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

        $path = BlockPath::parse($blockPath);

        if (null === $path || !\is_array($data['destinations'] ?? null)) {
            return;
        }

        $destinations = &$data['destinations'];

        if (!isset($destinations[$path->destinationIndex]) || !\is_array($destinations[$path->destinationIndex])) {
            return;
        }

        $destination = &$destinations[$path->destinationIndex];

        if ($path->isDestination()) {
            $destination['_feedback'] ??= $serialized;
            return;
        }

        if (null === $path->sectionIndex || !\is_array($destination['sections'] ?? null)) {
            return;
        }

        $sections = &$destination['sections'];

        if (!isset($sections[$path->sectionIndex]) || !\is_array($sections[$path->sectionIndex])) {
            return;
        }

        $section = &$sections[$path->sectionIndex];

        if ($path->isSection()) {
            $section['_feedback'] ??= $serialized;
            return;
        }

        if (null === $path->blockIndex || !\is_array($section['blocks'] ?? null)) {
            return;
        }

        $blocks = &$section['blocks'];

        if (isset($blocks[$path->blockIndex]) && \is_array($blocks[$path->blockIndex])) {
            $blocks[$path->blockIndex]['_feedback'] ??= $serialized;
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

        $path = BlockPath::parse($blockPath);
        $destinations = \is_array($data['destinations'] ?? null) ? $data['destinations'] : [];
        $destination = null !== $path ? ($destinations[$path->destinationIndex] ?? []) : [];

        if (null === $path || !\is_array($destination)) {
            return $feedback->getBlockType() ?? 'Reisplanonderdeel';
        }

        if ($path->isDestination()) {
            $title = $this->feedbackLabelValue($destination['title'] ?? null);

            return \sprintf(
                'Bestemming %d: %s',
                $path->destinationIndex + 1,
                '' !== $title ? $title : 'Bestemming',
            );
        }

        $sections = \is_array($destination['sections'] ?? null) ? $destination['sections'] : [];
        $section = null !== $path->sectionIndex ? ($sections[$path->sectionIndex] ?? []) : [];

        if (!\is_array($section)) {
            return $feedback->getBlockType() ?? 'Reisplanonderdeel';
        }

        if ($path->isSection()) {
            $title = $this->feedbackLabelValue($section['title'] ?? null);

            return \sprintf(
                'Bestemming %d, sectie %d: %s',
                $path->destinationIndex + 1,
                $path->sectionIndex + 1,
                '' !== $title ? $title : ($feedback->getBlockType() ?? 'Onderdeel'),
            );
        }

        $blocks = \is_array($section['blocks'] ?? null) ? $section['blocks'] : [];
        $block = null !== $path->blockIndex ? ($blocks[$path->blockIndex] ?? []) : [];
        $dayNumber = $this->feedbackLabelValue($section['dayNumber'] ?? null);
        $dayLabel = '' !== $dayNumber
            ? \sprintf('dag %d', (int) $dayNumber)
            : \sprintf('sectie %d', $path->sectionIndex + 1);
        $title = \is_array($block) ? $this->feedbackLabelValue($block['title'] ?? null) : '';

        return \sprintf(
            'Bestemming %d, %s: %s',
            $path->destinationIndex + 1,
            $dayLabel,
            '' !== $title ? $title : ($feedback->getBlockType() ?? 'Dagonderdeel'),
        );
    }

    private function feedbackLabelValue(mixed $value): string
    {
        return \is_scalar($value) ? \trim((string) $value) : '';
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
