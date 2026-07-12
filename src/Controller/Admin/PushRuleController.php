<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Admin\PushRuleAdmin;
use App\Entity\PushRule;
use App\Repository\PushRuleRepository;
use App\PushMessage\PushRuleManager;
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

final class PushRuleController extends AbstractRestController implements SecuredControllerInterface
{
    public function __construct(
        ViewHandlerInterface $viewHandler,
        private readonly RestHelperInterface $restHelper,
        private readonly FieldDescriptorFactoryInterface $fieldDescriptorFactory,
        private readonly DoctrineListBuilderFactoryInterface $listBuilderFactory,
        private readonly PushRuleRepository $repository,
        private readonly PushRuleManager $pushRuleManager,
    ) {
        parent::__construct($viewHandler);
    }

    public function cgetAction(): Response
    {
        $fieldDescriptors = $this->fieldDescriptorFactory->getFieldDescriptors(PushRuleAdmin::LIST_KEY);
        $listBuilder = $this->listBuilderFactory->create(PushRule::class);
        $this->restHelper->initializeListBuilder($listBuilder, $fieldDescriptors ?? []);

        $list = new PaginatedRepresentation(
            $listBuilder->execute(),
            PushRuleAdmin::RESOURCE_KEY,
            (int) $listBuilder->getCurrentPage(),
            (int) $listBuilder->getLimit(),
            (int) $listBuilder->count(),
        );

        return $this->handleView($this->view($list));
    }

    public function getAction(int $id): Response
    {
        return $this->handleView($this->view($this->serialize($this->findPushRule($id))));
    }

    public function postAction(Request $request): Response
    {
        $pushRule = $this->pushRuleManager->create($request->getPayload()->all());

        return $this->handleView($this->view($this->serialize($pushRule), Response::HTTP_CREATED));
    }

    public function putAction(Request $request, int $id): Response
    {
        $pushRule = $this->pushRuleManager->update($this->findPushRule($id), $request->getPayload()->all());

        return $this->handleView($this->view($this->serialize($pushRule)));
    }

    public function deleteAction(int $id): Response
    {
        $this->pushRuleManager->delete($this->findPushRule($id));

        return $this->handleView($this->view(null, Response::HTTP_NO_CONTENT));
    }

    public function cdeleteAction(Request $request): Response
    {
        $ids = \array_filter(\array_map('trim', \explode(',', (string) $request->query->get('ids', ''))));

        if ([] === $ids) {
            throw new BadRequestHttpException('No push rule ids given.');
        }

        foreach ($ids as $id) {
            if (1 !== \preg_match('/^\d+$/D', $id)) {
                throw new BadRequestHttpException('Invalid push rule id.');
            }

            $this->pushRuleManager->delete($this->findPushRule((int) $id));
        }

        return $this->handleView($this->view(null, Response::HTTP_NO_CONTENT));
    }

    public function getSecurityContext(): string
    {
        return PushRuleAdmin::SECURITY_CONTEXT;
    }

    public function getLocale(Request $request): string
    {
        $locale = $request->query->get('locale');
        if (\is_string($locale)) {
            return $locale;
        }

        return $request->getLocale();
    }

    private function findPushRule(int $id): PushRule
    {
        $pushRule = $this->repository->find($id);

        if (!$pushRule instanceof PushRule) {
            throw new NotFoundHttpException(\sprintf('PushRule "%d" was not found.', $id));
        }

        return $pushRule;
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(PushRule $pushRule): array
    {
        return [
            'id' => $pushRule->getId(),
            'name' => $pushRule->getName(),
            'triggerType' => $pushRule->getTriggerType(),
            'channel' => $pushRule->getChannel(),
            'active' => $pushRule->isActive(),
            'localTime' => $pushRule->getLocalTime(),
            'offsetValue' => $pushRule->getOffsetValue(),
            'offsetUnit' => $pushRule->getOffsetUnit(),
            'timezoneStrategy' => $pushRule->getTimezoneStrategy(),
            'titleTemplate' => $pushRule->getTitleTemplate(),
            'bodyTemplate' => $pushRule->getBodyTemplate(),
            'actionType' => $pushRule->getActionType() ?? PushRule::ACTION_TYPE_NONE,
            'actionTarget' => $pushRule->getActionTarget(),
            'createdAt' => $pushRule->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $pushRule->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
