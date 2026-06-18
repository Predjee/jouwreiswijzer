<?php

declare(strict_types=1);

namespace App\Api\App\Mapper;

use App\Api\App\Dto\ApiSection;
use App\Api\App\Dto\ScreenEnvelope;
use App\Api\App\ReadModel\NotificationReadModel;

final class NotificationsMapper
{
    /**
     * @return array<string, mixed>
     */
    public function map(NotificationReadModel $notifications): array
    {
        $sections = [
            new ApiSection('summary', [
                'title' => 'Meldingen',
                'unreadCount' => $notifications->unreadCount,
            ]),
        ];

        if ($notifications->hasItems()) {
            $sections[] = new ApiSection('notification_list', [
                'items' => $notifications->items,
            ]);
        } else {
            $sections[] = new ApiSection('empty_state', [
                'title' => 'Geen meldingen',
                'text' => 'Je hebt op dit moment geen meldingen.',
            ]);
        }

        return (new ScreenEnvelope(
            screen: 'notifications',
            sections: $sections,
            extra: [
                'title' => 'Meldingen',
            ],
        ))->toArray();
    }
}
