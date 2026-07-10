<?php

declare(strict_types=1);

namespace App\Service\TravelCompanion;

use App\Entity\TravelPlan;
use App\TravelPlan\Content\SectionType;
use App\TravelPlan\Content\TravelPlanContent;

final readonly class ReminderPlanBuilder
{
    private const FALLBACK_TIMEZONE = 'Europe/Amsterdam';

    /**
     * @return list<array{
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
     * }>
     */
    public function buildForRange(TravelPlan $travelPlan, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $content = TravelPlanContent::fromArray($travelPlan->getContent());
        $startDate = CompanionContentHelper::createDate($content->tripProfile->startDate);

        if (!$startDate instanceof \DateTimeImmutable || $to < $from) {
            return [];
        }

        $reminders = [];
        foreach ($content->destinations as $destination) {
            foreach ($destination->sections as $section) {
                if (SectionType::Day !== $section->type) {
                    continue;
                }

                $dayNumber = \max(1, (int) ($section->dayNumber ?: 1));
                $dayDate = $startDate->modify(\sprintf('+%d days', $dayNumber - 1));

                if ($dayDate < $from || $dayDate >= $to) {
                    continue;
                }

                $timezone = $this->timezone($section->destinationTimezone);
                foreach ($section->blocks as $block) {
                    $timeLabel = $block->timeLabel;
                    $time = $block->startTime ?: $block->time;

                    if (!$this->isValidTime($time)) {
                        continue;
                    }

                    $localTrigger = new \DateTimeImmutable(
                        $dayDate->format('Y-m-d') . ' ' . $time,
                        $timezone,
                    );

                    if (!$this->isInReminderWindow($localTrigger)) {
                        continue;
                    }

                    $triggerAt = $localTrigger->setTimezone(new \DateTimeZone('UTC'));

                    if ($triggerAt < $from || $triggerAt >= $to) {
                        continue;
                    }

                    $reminders[] = [
                        'triggerAt' => $triggerAt,
                        'tripId' => $travelPlan->getId(),
                        'dayNumber' => $dayNumber,
                        'dayTitle' => $section->title,
                        'destinationTitle' => $destination->title,
                        'blockType' => $block->type->value,
                        'title' => $block->title,
                        'text' => $block->text,
                        'location' => $block->location,
                        'timeLabel' => $timeLabel,
                        'icon' => $block->iconOrDefault(),
                        'bookingUrl' => $block->bookingUrl,
                    ];
                }
            }
        }

        \usort(
            $reminders,
            static fn (array $left, array $right): int => $left['triggerAt'] <=> $right['triggerAt'],
        );

        return $reminders;
    }

    private function timezone(string $identifier): \DateTimeZone
    {
        $identifier = \trim($identifier);

        if ('' === $identifier) {
            $identifier = self::FALLBACK_TIMEZONE;
        }

        try {
            return new \DateTimeZone($identifier);
        } catch (\Exception) {
            return new \DateTimeZone(self::FALLBACK_TIMEZONE);
        }
    }

    private function isValidTime(string $time): bool
    {
        return 1 === \preg_match('/^([01]\d|2[0-3]):[0-5]\d$/D', $time);
    }

    private function isInReminderWindow(\DateTimeImmutable $triggerAt): bool
    {
        $time = $triggerAt->format('H:i');

        return $time >= '08:00' && $time <= '22:00';
    }
}
