<?php

declare(strict_types=1);

namespace App\Controller\Api\App;

use App\Api\App\Command\MarkNotificationReadCommand;
use App\Api\App\CommandHandler\MarkNotificationReadCommandHandler;
use App\Api\App\Mapper\NotificationsMapper;
use App\Api\App\Query\GetNotificationsQuery;
use App\Api\App\QueryHandler\GetNotificationsQueryHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class NotificationController extends AbstractController
{
    use ApiAppCustomerTrait;

    #[Route('/api/app/notifications', name: 'api_app_notifications', methods: ['GET'])]
    public function __invoke(
        GetNotificationsQueryHandler $handler,
        NotificationsMapper $mapper,
    ): JsonResponse {
        [, $contact] = $this->getApiCustomer();

        return new JsonResponse($mapper->map($handler->handle(new GetNotificationsQuery($contact))));
    }

    #[Route('/api/app/notifications/{id}/read', name: 'api_app_notification_read', methods: ['POST'])]
    public function read(
        int $id,
        ValidatorInterface $validator,
        MarkNotificationReadCommandHandler $handler,
    ): JsonResponse {
        [, $contact] = $this->getApiCustomer();
        $command = new MarkNotificationReadCommand($id, $contact);

        if (\count($validator->validate($command)) > 0) {
            throw new NotFoundHttpException();
        }

        return new JsonResponse($handler->handle($command));
    }
}
