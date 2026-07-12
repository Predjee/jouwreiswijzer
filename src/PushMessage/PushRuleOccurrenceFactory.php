<?php

declare(strict_types=1);

namespace App\PushMessage;

use App\Entity\PushRule;
use App\Entity\TravelPlan;
use App\Companion\CompanionContentHelper;
use App\TravelPlan\Content\BlockType;
use App\TravelPlan\Content\DayBlock;
use App\TravelPlan\Content\Section;
use App\TravelPlan\Content\SectionType;
use App\TravelPlan\Content\TravelPlanContent;

final readonly class PushRuleOccurrenceFactory
{
    private const DEFAULT_LOCAL_TIME = '09:00';
    private const FALLBACK_TIMEZONE = 'Europe/Amsterdam';

    public function __construct(
        private PushMessageTemplateRenderer $templateRenderer,
    ) {
    }

    /**
     * @return list<PushRuleOccurrence>
     */
    public function createOccurrences(PushRule $rule, TravelPlan $travelPlan): array
    {
        if (!$rule->isActive() || null === $rule->getId() || null === $travelPlan->getId()) {
            return [];
        }

        return match ($rule->getTriggerType()) {
            PushRule::TRIGGER_TYPE_TRIP_START_OFFSET => $this->tripDateOffsetOccurrences($rule, $travelPlan, 'trip_start'),
            PushRule::TRIGGER_TYPE_TRIP_END_OFFSET => $this->tripDateOffsetOccurrences($rule, $travelPlan, 'trip_end'),
            PushRule::TRIGGER_TYPE_DAY_START => $this->dayStartOccurrences($rule, $travelPlan),
            PushRule::TRIGGER_TYPE_ACTIVITY_START_OFFSET => $this->activityStartOffsetOccurrences($rule, $travelPlan),
            default => [],
        };
    }

    /**
     * @return list<PushRuleOccurrence>
     */
    private function tripDateOffsetOccurrences(PushRule $rule, TravelPlan $travelPlan, string $anchor): array
    {
        $content = TravelPlanContent::fromArray($travelPlan->getContent());
        $tripProfile = $content->tripProfile->raw;
        $date = CompanionContentHelper::createDate('trip_start' === $anchor ? $content->tripProfile->startDate : $content->tripProfile->endDate);

        if (!$date instanceof \DateTimeImmutable || !$this->hasOffset($rule)) {
            return [];
        }

        $timezone = $this->timezone($rule, null, $this->tripTimezone($tripProfile));
        $scheduledFor = new \DateTimeImmutable(
            $date->format('Y-m-d').' '.($rule->getLocalTime() ?? self::DEFAULT_LOCAL_TIME),
            $timezone,
        );

        return [
            $this->createOccurrence(
                $rule,
                $travelPlan,
                \sprintf('rule_%d:trip_%d:%s', $rule->getId(), $travelPlan->getId(), $anchor),
                $this->applyOffset($scheduledFor, $rule),
            ),
        ];
    }

    /**
     * @return list<PushRuleOccurrence>
     */
    private function dayStartOccurrences(PushRule $rule, TravelPlan $travelPlan): array
    {
        $localTime = $rule->getLocalTime();

        if (null === $localTime) {
            return [];
        }

        $content = TravelPlanContent::fromArray($travelPlan->getContent());
        $tripProfile = $content->tripProfile->raw;
        $startDate = CompanionContentHelper::createDate($content->tripProfile->startDate);
        $endDate = CompanionContentHelper::createDate($content->tripProfile->endDate);

        if (!$startDate instanceof \DateTimeImmutable || !$endDate instanceof \DateTimeImmutable || $endDate < $startDate) {
            return [];
        }

        $sectionsByDayNumber = $this->sectionsByDayNumber($content);
        $tripTimezone = $this->tripTimezone($tripProfile);
        $occurrences = [];

        foreach (\range(1, CompanionContentHelper::inclusiveDays($startDate, $endDate)) as $dayNumber) {
            $section = $sectionsByDayNumber[$dayNumber] ?? null;
            $dayDate = $startDate->modify(\sprintf('+%d days', $dayNumber - 1));
            $timezone = $this->timezone($rule, $this->dayTimezone(null !== $section ? $section->destinationTimezone : ''), $tripTimezone);
            $scheduledFor = new \DateTimeImmutable($dayDate->format('Y-m-d').' '.$localTime, $timezone);

            $occurrences[] = $this->createOccurrence(
                $rule,
                $travelPlan,
                \sprintf('rule_%d:trip_%d:day_%d', $rule->getId(), $travelPlan->getId(), $dayNumber),
                $scheduledFor,
                [
                    'number' => $dayNumber,
                    'title' => (null !== $section ? $section->title : '') ?: \sprintf('Dag %d', $dayNumber),
                    'date' => $dayDate,
                ],
            );
        }

        return $occurrences;
    }

    /**
     * @return list<PushRuleOccurrence>
     */
    private function activityStartOffsetOccurrences(PushRule $rule, TravelPlan $travelPlan): array
    {
        if (!$this->hasOffset($rule)) {
            return [];
        }

        $content = TravelPlanContent::fromArray($travelPlan->getContent());
        $tripProfile = $content->tripProfile->raw;
        $startDate = CompanionContentHelper::createDate($content->tripProfile->startDate);

        if (!$startDate instanceof \DateTimeImmutable) {
            return [];
        }

        $tripTimezone = $this->tripTimezone($tripProfile);
        $occurrences = [];

        foreach ($content->destinations as $destination) {
            foreach ($destination->sections as $section) {
                if (SectionType::Day !== $section->type) {
                    continue;
                }

                $dayNumber = \max(1, (int) ($section->dayNumber ?: 1));
            $dayDate = $startDate->modify(\sprintf('+%d days', $dayNumber - 1));
                $timezone = $this->timezone($rule, $this->dayTimezone($section->destinationTimezone), $tripTimezone);

                foreach ($section->blocks as $block) {
                    if (BlockType::Activity !== $block->type) {
                        continue;
                    }

                    $time = $this->timeValue($block);

                    if (null === $time) {
                        continue;
                    }

                    $scheduledFor = new \DateTimeImmutable($dayDate->format('Y-m-d').' '.$time, $timezone);

                    $occurrences[] = $this->createOccurrence(
                        $rule,
                        $travelPlan,
                        \sprintf(
                            'rule_%d:trip_%d:day_%d:block_%d',
                            $rule->getId(),
                            $travelPlan->getId(),
                            $dayNumber,
                            $block->sourceIndex + 1,
                        ),
                        $this->applyOffset($scheduledFor, $rule),
                        [
                            'number' => $dayNumber,
                            'title' => $section->title ?: \sprintf('Dag %d', $dayNumber),
                            'date' => $dayDate,
                        ],
                    );
                }
            }
        }

        return $occurrences;
    }

    /**
     * @param array{number?: int, title?: string, date?: \DateTimeImmutable} $day
     */
    private function createOccurrence(
        PushRule $rule,
        TravelPlan $travelPlan,
        string $sourceKey,
        \DateTimeImmutable $scheduledFor,
        array $day = [],
    ): PushRuleOccurrence {
        return new PushRuleOccurrence(
            $sourceKey,
            $scheduledFor,
            $this->templateRenderer->render($rule->getTitleTemplate(), $travelPlan, $day),
            $this->templateRenderer->render($rule->getBodyTemplate(), $travelPlan, $day),
            $rule->getChannel(),
            $rule->getActionType(),
            $rule->getActionTarget(),
        );
    }

    private function hasOffset(PushRule $rule): bool
    {
        return null !== $rule->getOffsetValue() && null !== $rule->getOffsetUnit();
    }

    private function applyOffset(\DateTimeImmutable $dateTime, PushRule $rule): \DateTimeImmutable
    {
        return $dateTime->modify(\sprintf('%+d %s', $rule->getOffsetValue() ?? 0, $rule->getOffsetUnit() ?? PushRule::OFFSET_UNIT_DAYS));
    }

    /**
     * @param array<string, mixed> $tripProfile
     */
    private function tripTimezone(array $tripProfile): ?\DateTimeZone
    {
        foreach (['tripTimezone', 'timezone', 'destinationTimezone'] as $key) {
            $timezone = $this->validTimezone(CompanionContentHelper::stringValue($tripProfile, $key));

            if ($timezone instanceof \DateTimeZone) {
                return $timezone;
            }
        }

        return null;
    }

    private function dayTimezone(string $timezone): ?\DateTimeZone
    {
        return $this->validTimezone($timezone);
    }

    private function timezone(PushRule $rule, ?\DateTimeZone $dayTimezone, ?\DateTimeZone $tripTimezone): \DateTimeZone
    {
        if (PushRule::TIMEZONE_STRATEGY_DAY === $rule->getTimezoneStrategy() && $dayTimezone instanceof \DateTimeZone) {
            return $dayTimezone;
        }

        if (\in_array($rule->getTimezoneStrategy(), [PushRule::TIMEZONE_STRATEGY_DAY, PushRule::TIMEZONE_STRATEGY_TRIP], true)
            && $tripTimezone instanceof \DateTimeZone
        ) {
            return $tripTimezone;
        }

        return new \DateTimeZone(self::FALLBACK_TIMEZONE);
    }

    private function validTimezone(string $identifier): ?\DateTimeZone
    {
        $identifier = \trim($identifier);

        if ('' === $identifier) {
            return null;
        }

        try {
            return new \DateTimeZone($identifier);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * @return array<int, Section>
     */
    private function sectionsByDayNumber(TravelPlanContent $content): array
    {
        $indexed = [];

        foreach ($content->destinations as $destination) {
            foreach ($destination->sections as $section) {
                if (SectionType::Day !== $section->type) {
                    continue;
                }

                $indexed[\max(1, (int) ($section->dayNumber ?: 1))] = $section;
            }
        }

        return $indexed;
    }

    private function timeValue(DayBlock $block): ?string
    {
        foreach ([$block->startTime, $block->time] as $time) {
            if (1 === \preg_match('/^([01]\d|2[0-3]):[0-5]\d$/D', $time)) {
                return $time;
            }
        }

        return null;
    }
}
