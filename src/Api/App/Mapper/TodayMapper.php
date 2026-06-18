<?php

declare(strict_types=1);

namespace App\Api\App\Mapper;

use App\Api\App\Dto\ApiSection;
use App\Api\App\Dto\ScreenEnvelope;
use App\Api\App\ReadModel\TodayReadModel;

final class TodayMapper
{
    /**
     * @return array<string, mixed>
     */
    public function map(TodayReadModel $today): array
    {
        $sections = [
            new ApiSection('hero', [
                'active' => $today->active,
                'currentDayNumber' => $today->currentDayNumber,
                'totalDays' => $today->totalDays,
                'periodLabel' => $today->periodLabel,
                'durationLabel' => $today->durationLabel,
                'timingLabel' => $today->timingLabel,
                'dayTitle' => $today->dayTitle,
                'dayDateLabel' => $today->dayDateLabel,
                'dayIntro' => $today->dayIntro,
            ]),
        ];

        if (null !== $today->nextActivity) {
            $sections[] = new ApiSection('next_activity', [
                'item' => $today->nextActivity,
            ]);
        }

        if ([] !== $today->timelineItems) {
            $sections[] = new ApiSection('timeline', [
                'items' => $today->timelineItems,
            ]);
        }

        if ([] !== $today->tips) {
            $sections[] = new ApiSection('tips', [
                'items' => $today->tips,
            ]);
        }

        if (!$today->active || !$today->hasDayContent()) {
            $sections[] = new ApiSection('empty_state', [
                'reason' => $today->active ? 'day_not_available' : 'trip_not_active',
                'title' => $today->active ? 'Dagplanning nog niet beschikbaar' : 'Vandaag geen actieve reisdag',
                'text' => $today->active
                    ? 'De dagplanning voor vandaag is nog niet ingevuld.'
                    : 'Deze reis valt niet op de datum van vandaag.',
            ]);
        }

        $envelope = new ScreenEnvelope(
            screen: 'today',
            sections: $sections,
            extra: [
                'trip' => [
                    'id' => $today->tripId,
                    'title' => $today->tripTitle,
                    'pdfAvailable' => $today->pdfAvailable,
                ],
            ],
        );

        return $envelope->toArray();
    }
}
