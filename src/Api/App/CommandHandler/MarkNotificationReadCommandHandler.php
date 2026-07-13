<?php

declare(strict_types=1);

namespace App\Api\App\CommandHandler;

use App\Api\App\Command\MarkNotificationReadCommand;
use App\Entity\Notification;
use App\Notification\NotificationService;
use App\Repository\NotificationRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class MarkNotificationReadCommandHandler
{
    public function __construct(
        private NotificationRepository $notificationRepository,
        private NotificationService $notificationService,
    ) {
    }

    /**
     * @return array{id: int, read: bool, unreadCount: int}
     */
    public function handle(MarkNotificationReadCommand $command): array
    {
        $notification = $this->notificationRepository->find($command->notificationId);

        if (
            !$notification instanceof Notification
            || $notification->getRecipientContact()?->getId() !== $command->contact->getId()
        ) {
            throw new NotFoundHttpException();
        }

        $this->notificationService->markAsRead($notification);

        return [
            'id' => $notification->getId() ?? 0,
            'read' => $notification->isRead(),
            'unreadCount' => $this->notificationRepository->countUnreadForContact($command->contact),
        ];
    }
}
