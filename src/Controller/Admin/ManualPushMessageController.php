<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Admin\PushRuleAdmin;
use App\Entity\ScheduledPushMessage;
use App\Entity\TravelPlan;
use App\PushMessage\PushMessageTemplateRenderer;
use App\PushMessage\TravelPlanPersonalizationContextBuilder;
use App\Repository\ScheduledPushMessageRepository;
use App\Repository\TravelPlanRepository;
use App\Service\ManualPushMessageManager;
use FOS\RestBundle\View\ViewHandlerInterface;
use Sulu\Component\Rest\AbstractRestController;
use Sulu\Component\Rest\ListBuilder\PaginatedRepresentation;
use Sulu\Component\Security\SecuredControllerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ManualPushMessageController extends AbstractRestController implements SecuredControllerInterface
{
    public function __construct(
        ViewHandlerInterface $viewHandler,
        private readonly ScheduledPushMessageRepository $repository,
        private readonly TravelPlanRepository $travelPlanRepository,
        private readonly ManualPushMessageManager $manager,
        private readonly TravelPlanPersonalizationContextBuilder $contextBuilder,
        private readonly PushMessageTemplateRenderer $templateRenderer,
    ) {
        parent::__construct($viewHandler);
    }

    public function cgetAction(): Response
    {
        $messages = $this->repository->createQueryBuilder('message')
            ->andWhere('message.pushRule IS NULL')
            ->orderBy('message.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
        $items = \array_map(
            fn (ScheduledPushMessage $message): array => $this->serializeListItem($message),
            $messages,
        );

        $list = new PaginatedRepresentation(
            $items,
            PushRuleAdmin::MANUAL_RESOURCE_KEY,
            1,
            \max(1, \count($items)),
            \count($items),
        );

        return $this->handleView($this->view($list));
    }

    public function getAction(int $id): Response
    {
        return $this->handleView($this->view($this->serialize($this->findMessage($id))));
    }

    public function postAction(Request $request): Response
    {
        $message = $this->manager->create($request->getPayload()->all());

        return $this->handleView($this->view($this->serialize($message), Response::HTTP_CREATED));
    }

    public function putAction(Request $request, int $id): Response
    {
        $message = $this->manager->update($this->findMessage($id), $request->getPayload()->all());

        return $this->handleView($this->view($this->serialize($message)));
    }

    public function optionsAction(Request $request): Response
    {
        $query = \trim($request->query->getString('search'));
        $selectedId = $request->query->get('selected');
        $qb = $this->travelPlanRepository->createQueryBuilder('travelPlan')
            ->innerJoin('travelPlan.travelRequest', 'travelRequest')
            ->innerJoin('travelRequest.contact', 'contact')
            ->orderBy('travelPlan.id', 'DESC')
            ->setMaxResults(50);

        if ('' !== $query) {
            $qb
                ->andWhere('LOWER(travelPlan.title) LIKE :query OR LOWER(contact.firstName) LIKE :query OR LOWER(contact.lastName) LIKE :query')
                ->setParameter('query', '%'.\strtolower($query).'%');
        }

        $travelPlans = $qb->getQuery()->getResult();

        if (\is_string($selectedId) && 1 === \preg_match('/^\d+$/D', $selectedId)) {
            $selected = $this->travelPlanRepository->find((int) $selectedId);

            if ($selected instanceof TravelPlan && !\in_array($selected, $travelPlans, true)) {
                \array_unshift($travelPlans, $selected);
            }
        }

        $items = \array_map(
            fn (TravelPlan $travelPlan): array => $this->serializeTravelPlanOption($travelPlan),
            $travelPlans,
        );

        return $this->handleView($this->view(['_embedded' => ['travelPlans' => $items], 'total' => \count($items)]));
    }

    public function previewAction(Request $request): Response
    {
        $data = $request->getPayload()->all();
        $travelPlanId = $data['travelPlanId'] ?? null;

        if (!\is_int($travelPlanId) && !(\is_string($travelPlanId) && 1 === \preg_match('/^\d+$/D', $travelPlanId))) {
            throw new BadRequestHttpException('Travel plan is required for preview.');
        }

        $travelPlan = $this->travelPlanRepository->find((int) $travelPlanId);

        if (!$travelPlan instanceof TravelPlan) {
            throw new NotFoundHttpException(\sprintf('TravelPlan "%d" was not found.', (int) $travelPlanId));
        }

        $context = $this->contextBuilder->build($travelPlan, $travelPlan->getTravelRequest()->getContact());
        $titleTemplate = \is_scalar($data['titleTemplate'] ?? null) ? (string) $data['titleTemplate'] : '';
        $bodyTemplate = \is_scalar($data['bodyTemplate'] ?? null) ? (string) $data['bodyTemplate'] : '';

        return $this->handleView($this->view([
            'renderedTitle' => $this->templateRenderer->renderWithValues($titleTemplate, $context['values']),
            'renderedBody' => $this->templateRenderer->renderWithValues($bodyTemplate, $context['values']),
            'availableTokens' => $context['groups'],
        ]));
    }

    public function getSecurityContext(): string
    {
        return PushRuleAdmin::SECURITY_CONTEXT;
    }

    public function getLocale(Request $request): string
    {
        $locale = $request->query->get('locale');

        return \is_string($locale) ? $locale : $request->getLocale();
    }

    private function findMessage(int $id): ScheduledPushMessage
    {
        $message = $this->repository->find($id);

        if (!$message instanceof ScheduledPushMessage) {
            throw new NotFoundHttpException(\sprintf('ScheduledPushMessage "%d" was not found.', $id));
        }

        return $message;
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(ScheduledPushMessage $message): array
    {
        return [
            'id' => $message->getId(),
            'travelPlanId' => $message->getTravelPlan()->getId(),
            'travelPlanLabel' => $this->travelPlanLabel($message->getTravelPlan()),
            'channel' => $message->getChannel(),
            'titleTemplate' => $message->getTitle(),
            'bodyTemplate' => $message->getBody(),
            'scheduledFor' => $message->getScheduledFor()->format(\DateTimeInterface::ATOM),
            'sendNow' => false,
            'status' => $message->getStatus(),
            'createdAt' => $message->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeListItem(ScheduledPushMessage $message): array
    {
        return [
            'id' => $message->getId(),
            'title' => $message->getTitle(),
            'travelPlanTitle' => $message->getTravelPlan()->getTitle(),
            'channel' => $message->getChannel(),
            'scheduledFor' => $message->getScheduledFor()->format(\DateTimeInterface::ATOM),
            'status' => $message->getStatus(),
            'createdAt' => $message->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return array{id: int|null, title: string, label: string}
     */
    private function serializeTravelPlanOption(TravelPlan $travelPlan): array
    {
        return [
            'id' => $travelPlan->getId(),
            'title' => $travelPlan->getTitle(),
            'label' => $this->travelPlanLabel($travelPlan),
        ];
    }

    private function travelPlanLabel(TravelPlan $travelPlan): string
    {
        $contact = $travelPlan->getTravelRequest()->getContact();
        $contactName = \trim($contact->getFullName());

        if ('' === $contactName) {
            $contactName = \trim($contact->getFirstName().' '.$contact->getLastName());
        }

        return '' === $contactName
            ? $travelPlan->getTitle()
            : \sprintf('%s - %s', $travelPlan->getTitle(), $contactName);
    }
}
