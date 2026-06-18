<?php

declare(strict_types=1);

namespace App\Api\App\Mapper;

use App\Api\App\Dto\ApiSection;
use App\Api\App\Dto\ScreenEnvelope;
use App\ViewModel\TravelCompanion\CompanionBlock;
use App\ViewModel\TravelCompanion\CompanionDay;
use App\ViewModel\TravelCompanion\CompanionTrip;

final class TripDetailMapper
{
    /**
     * @return array{
     *     screen: string,
     *     version: int,
     *     trip: array{id: int, title: string, pdfAvailable: bool},
     *     sections: list<array{type: string, data: array<string, mixed>}>
     * }
     */
    public function map(CompanionTrip $trip): array
    {
        $sections = [
            new ApiSection('hero_summary', [
                'periodLabel' => $trip->periodLabel,
                'durationLabel' => $trip->durationLabel,
                'dayStatusLabel' => $trip->dayStatusLabel,
                'currentDayNumber' => $trip->currentDayNumber,
            ]),
        ];

        if ($trip->hasDays()) {
            $sections[] = new ApiSection('timeline', [
                'days' => \array_map(
                    static fn (CompanionDay $day): array => [
                        'dayNumber' => $day->dayNumber,
                        'status' => $day->status,
                        'title' => $day->title,
                        'dateLabel' => $day->dateLabel,
                    ],
                    $trip->days,
                ),
            ]);
        }

        foreach ($trip->blocks as $block) {
            $sections[] = $this->mapBlock($block);
        }

        $envelope = new ScreenEnvelope(
            screen: 'trip_detail',
            sections: $sections,
            extra: [
                'trip' => [
                    'id' => $trip->id,
                    'title' => $trip->title,
                    'pdfAvailable' => $trip->pdfAvailable,
                ],
            ],
        );

        return $envelope->toArray();
    }

    private function mapBlock(CompanionBlock $block): ApiSection
    {
        if ('checklist' === $block->type) {
            return new ApiSection('checklist', [
                'items' => $block->checklistItems,
            ]);
        }

        if ('route_overview' === $block->type) {
            return new ApiSection('route_overview', [
                'stops' => $block->routeStops,
            ]);
        }

        return new ApiSection('info_block', [
            'title' => $block->title,
            'text' => $block->text,
            'icon' => $block->icon,
            'location' => $block->location,
            'timeLabel' => $block->timeLabel,
            'startTime' => $block->startTime,
            'endTime' => $block->endTime,
            'timeRangeLabel' => $block->timeRangeLabel,
        ]);
    }
}
