<?php

declare(strict_types=1);

namespace App\Api\App\QueryHandler;

use App\Api\App\Query\GetNotificationsQuery;
use App\Api\App\ReadModel\NotificationReadModel;
use App\Entity\Notification;
use App\Repository\NotificationRepository;

final readonly class GetNotificationsQueryHandler
{
    public function __construct(private NotificationRepository $notificationRepository)
    {
    }

    public function handle(GetNotificationsQuery $query): NotificationReadModel
    {
        $items = \array_map(
            fn (Notification $notification): array => $this->item($notification),
            $this->notificationRepository->findForContact($query->contact),
        );

        return new NotificationReadModel(
            $this->notificationRepository->countUnreadForContact($query->contact),
            $items,
        );
    }

    /**
     * @return array{
     *     id: int,
     *     type: string,
     *     title: string,
     *     message: string,
     *     read: bool,
     *     createdAt: string,
     *     action?: array<string, mixed>
     * }
     */
    private function item(Notification $notification): array
    {
        $item = [
            'id' => $notification->getId() ?? 0,
            'type' => $notification->getType(),
            'title' => $notification->getTitle(),
            'message' => $notification->getMessage(),
            'read' => $notification->isRead(),
            'createdAt' => $notification->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];

        $action = $this->action($notification);

        if (null !== $action) {
            $item['action'] = $action;
        }

        return $item;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function action(Notification $notification): ?array
    {
        $url = $notification->getUrl();

        if (null === $url || '' === \trim($url)) {
            return null;
        }

        $path = \parse_url($url, \PHP_URL_PATH);
        $query = \parse_url($url, \PHP_URL_QUERY);

        if (\is_string($path) && 1 === \preg_match('#^/account/travel-plans/(\d+)#', $path, $matches)) {
            $params = ['id' => (int) $matches[1]];

            if (\is_string($query)) {
                \parse_str($query, $queryParams);

                if (\is_string($queryParams['mode'] ?? null) && '' !== $queryParams['mode']) {
                    $params['mode'] = $queryParams['mode'];
                }
            }

            return [
                'type' => 'screen',
                'label' => 'Bekijk reis',
                'target' => 'trip',
                'params' => $params,
            ];
        }

        return [
            'type' => 'url',
            'label' => 'Openen',
            'url' => $url,
        ];
    }
}
