<?php

declare(strict_types=1);

namespace App\Api\App\Mapper;

use App\Api\App\Dto\ApiSection;
use App\Api\App\Dto\ScreenEnvelope;

final class ReminderMapper
{
    /**
     * @param list<array{
     *     triggerAt: \DateTimeImmutable,
     *     tripId: int|null,
     *     dayNumber: int,
     *     dayTitle: string,
     *     destinationTitle: string,
     *     blockType: string,
     *     title: string,
     *     text: string,
     *     location: string,
     *     timeLabel: string,
     *     icon: string,
     *     bookingUrl: string
     * }> $reminders
     *
     * @return array<string, mixed>
     */
    public function map(int $tripId, array $reminders): array
    {
        return (new ScreenEnvelope(
            screen: 'trip_reminders',
            sections: [
                new ApiSection('reminder_list', [
                    'reminders' => \array_map(
                        static fn (array $reminder): array => [
                            'triggerAt' => $reminder['triggerAt']->format(\DateTimeInterface::ATOM),
                            'tripId' => $reminder['tripId'],
                            'dayNumber' => $reminder['dayNumber'],
                            'dayTitle' => $reminder['dayTitle'],
                            'destinationTitle' => $reminder['destinationTitle'],
                            'blockType' => $reminder['blockType'],
                            'title' => $reminder['title'],
                            'text' => $reminder['text'],
                            'location' => $reminder['location'],
                            'timeLabel' => $reminder['timeLabel'],
                            'icon' => $reminder['icon'],
                            'bookingUrl' => $reminder['bookingUrl'],
                        ],
                        $reminders,
                    ),
                ]),
            ],
            extra: [
                'trip' => [
                    'id' => $tripId,
                ],
            ],
        ))->toArray();
    }
}
