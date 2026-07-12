<?php

declare(strict_types=1);

namespace App\Api\App\QueryHandler;

use App\Api\App\Query\GetTodayQuery;
use App\Api\App\ReadModel\TodayReadModel;
use App\Repository\TravelPlanRepository;
use App\Service\TravelCompanion\CompanionContentHelper;
use App\Service\TravelCompanion\TravelCompanionBuilder;
use App\ViewModel\TravelCompanion\CompanionBlock;
use App\ViewModel\TravelCompanion\CompanionDay;

final readonly class GetTodayQueryHandler
{
    public function __construct(
        private TravelPlanRepository $travelPlanRepository,
        private TravelCompanionBuilder $companionBuilder,
    ) {
    }

    public function handle(GetTodayQuery $query): ?TodayReadModel
    {
        $travelPlan = $this->travelPlanRepository->findPublishedForContact($query->tripId, $query->contact);

        if (null === $travelPlan) {
            return null;
        }

        $trip = $this->companionBuilder->build($travelPlan, $query->contact);
        $content = $travelPlan->getContent();
        $tripProfile = \is_array($content['tripProfile'] ?? null) ? $content['tripProfile'] : [];
        $startDate = CompanionContentHelper::createDate($tripProfile['startDate'] ?? null);
        $endDate = CompanionContentHelper::createDate($tripProfile['endDate'] ?? null);
        $totalDays = $this->totalDays($startDate, $endDate);
        $currentDay = null !== $trip->currentDayNumber ? $trip->findDay($trip->currentDayNumber) : null;
        $timelineItems = $this->timelineItems($currentDay);
        $tips = $this->tips($currentDay);

        return new TodayReadModel(
            $trip->id,
            $trip->title,
            $trip->pdfAvailable,
            null !== $trip->currentDayNumber,
            $trip->currentDayNumber,
            $totalDays,
            $trip->periodLabel,
            $trip->durationLabel,
            $trip->dayStatusLabel,
            $currentDay?->title,
            $currentDay?->dateLabel,
            $currentDay?->intro,
            $this->nextActivity($currentDay),
            $timelineItems,
            $tips,
        );
    }

    private function totalDays(?\DateTimeImmutable $startDate, ?\DateTimeImmutable $endDate): int
    {
        if ($startDate instanceof \DateTimeImmutable && $endDate instanceof \DateTimeImmutable && $endDate >= $startDate) {
            return CompanionContentHelper::inclusiveDays($startDate, $endDate);
        }

        return 0;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function nextActivity(?CompanionDay $day): ?array
    {
        if (!$day instanceof CompanionDay) {
            return null;
        }

        foreach ($day->blocks as $block) {
            if (!$this->isActivity($block)) {
                continue;
            }

            return $this->mapBlock($block);
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function timelineItems(?CompanionDay $day): array
    {
        if (!$day instanceof CompanionDay) {
            return [];
        }

        return \array_values(\array_map(
            fn (CompanionBlock $block): array => $this->mapBlock($block),
            \array_filter(
                $day->blocks,
                static fn (CompanionBlock $block): bool => !$block->isChecklist(),
            ),
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tips(?CompanionDay $day): array
    {
        if (!$day instanceof CompanionDay) {
            return [];
        }

        return \array_values(\array_map(
            fn (CompanionBlock $block): array => $this->mapBlock($block),
            \array_filter(
                $day->blocks,
                static fn (CompanionBlock $block): bool => \in_array($block->type, ['tip', 'note', 'personal_note'], true),
            ),
        ));
    }

    private function isActivity(CompanionBlock $block): bool
    {
        return \in_array($block->type, ['activity', 'accommodation', 'transport', 'meal'], true);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapBlock(CompanionBlock $block): array
    {
        return [
            'type' => $block->type,
            'title' => $block->title,
            'text' => $block->text,
            'icon' => $block->icon,
            'location' => $block->location,
            'timeLabel' => $block->timeLabel,
            'startTime' => $block->startTime,
            'endTime' => $block->endTime,
            'timeRangeLabel' => $block->timeRangeLabel,
            'mapsUrl' => $block->mapsUrl,
            'bookingUrl' => $block->bookingUrl,
        ];
    }
}
