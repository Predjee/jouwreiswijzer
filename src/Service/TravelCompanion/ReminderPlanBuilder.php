<?php

declare(strict_types=1);

namespace App\Service\TravelCompanion;

use App\Entity\TravelPlan;

final readonly class ReminderPlanBuilder
{
    private const FALLBACK_TIMEZONE = 'Europe/Amsterdam';

    /**
     * @return list<array{
     *     triggerAt: \DateTimeImmutable,
     *     tripId: int|null,
     *     dayNumber: int,
     *     dayTitle: string,
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
        $content = $travelPlan->getContent();
        $tripProfile = \is_array($content['tripProfile'] ?? null) ? $content['tripProfile'] : [];
        $startDate = CompanionContentHelper::createDate($tripProfile['startDate'] ?? null);

        if (!$startDate instanceof \DateTimeImmutable || $to < $from) {
            return [];
        }

        $reminders = [];

        foreach ($content['sections'] ?? [] as $section) {
            if (!\is_array($section) || 'day' !== ($section['type'] ?? null)) {
                continue;
            }

            $dayNumber = \max(1, (int) ($section['dayNumber'] ?? 1));
            $dayDate = $startDate->modify(\sprintf('+%d days', $dayNumber - 1));

            if ($dayDate < $from || $dayDate >= $to) {
                continue;
            }

            $timezone = $this->timezone(CompanionContentHelper::stringValue($section, 'destinationTimezone'));

            foreach ($section['blocks'] ?? [] as $block) {
                if (!\is_array($block)) {
                    continue;
                }

                $timeLabel = CompanionContentHelper::stringValue($block, 'timeLabel');
                $time = CompanionContentHelper::stringValue($block, 'startTime')
                    ?: CompanionContentHelper::stringValue($block, 'time')
                        ?: $timeLabel;

                if (!$this->isValidTime($time)) {
                    continue;
                }

                $triggerAt = new \DateTimeImmutable($dayDate->format('Y-m-d') . ' ' . $time, $timezone);

                if (!$this->isInReminderWindow($triggerAt)) {
                    continue;
                }

                $reminders[] = [
                    'triggerAt' => $triggerAt,
                    'tripId' => $travelPlan->getId(),
                    'dayNumber' => $dayNumber,
                    'dayTitle' => CompanionContentHelper::stringValue($section, 'title'),
                    'blockType' => CompanionContentHelper::stringValue($block, 'type'),
                    'title' => CompanionContentHelper::stringValue($block, 'title'),
                    'text' => CompanionContentHelper::stringValue($block, 'text'),
                    'location' => CompanionContentHelper::stringValue($block, 'location'),
                    'timeLabel' => $timeLabel ?: $time,
                    'icon' => CompanionContentHelper::stringValue($block, 'icon'),
                    'bookingUrl' => CompanionContentHelper::stringValue($block, 'bookingUrl'),
                ];
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
