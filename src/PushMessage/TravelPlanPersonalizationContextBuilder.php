<?php

declare(strict_types=1);

namespace App\PushMessage;

use App\Entity\TravelPlan;
use App\Service\TravelCompanion\CompanionContentHelper;
use App\TravelPlan\Content\BlockType;
use App\TravelPlan\Content\DayBlock;
use App\TravelPlan\Content\SectionType;
use App\TravelPlan\Content\TravelPlanContent;

final readonly class TravelPlanPersonalizationContextBuilder
{
    /**
     * @param array{number?: int, title?: string, date?: \DateTimeImmutable} $currentDay
     *
     * @return array{
     *     values: array<string, string>,
     *     groups: list<array{label: string, tokens: list<array{key: string, label: string, exampleValue: string, available: bool}>}>
     * }
     */
    public function build(TravelPlan $travelPlan, object $customer, array $currentDay = []): array
    {
        $content = TravelPlanContent::fromArray($travelPlan->getContent());
        $startDate = CompanionContentHelper::createDate($content->tripProfile->startDate);
        $endDate = CompanionContentHelper::createDate($content->tripProfile->endDate);
        $days = $this->days($content, $startDate);
        $selectedDay = $this->currentDay($days, $currentDay, $startDate, $endDate);
        $nextDay = $this->nextDay($days, $selectedDay);
        $nextActivity = $this->nextActivity($days, $selectedDay);

        $values = [
            'customer.firstName' => $this->customerFirstName($customer),
            'customer.fullName' => $this->customerFullName($customer),
            'trip.title' => $travelPlan->getTitle(),
            'trip.startDate' => $this->formatDate($startDate),
            'trip.endDate' => $this->formatDate($endDate),
            'trip.totalDays' => $this->totalDays($startDate, $endDate),
            'days.count' => [] === $days ? '' : (string) \count($days),
            'currentDay.number' => $this->stringFromArray($selectedDay, 'number'),
            'currentDay.title' => $this->stringFromArray($selectedDay, 'title'),
            'currentDay.date' => $this->formatDate($selectedDay['date'] ?? null),
            'nextDay.number' => $this->stringFromArray($nextDay, 'number'),
            'nextDay.title' => $this->stringFromArray($nextDay, 'title'),
            'nextActivity.title' => $this->stringFromArray($nextActivity, 'title'),
            'nextActivity.time' => $this->stringFromArray($nextActivity, 'time'),
            'nextActivity.location' => $this->stringFromArray($nextActivity, 'location'),
            'day.number' => $this->stringFromArray($selectedDay, 'number'),
            'day.title' => $this->stringFromArray($selectedDay, 'title'),
            'day.date' => $this->formatDate($selectedDay['date'] ?? null),
        ];

        return [
            'values' => $values,
            'groups' => [
                [
                    'label' => 'Klant',
                    'tokens' => [
                        $this->token('customer.firstName', 'Voornaam', $values),
                        $this->token('customer.fullName', 'Volledige naam', $values),
                    ],
                ],
                [
                    'label' => 'Reis',
                    'tokens' => [
                        $this->token('trip.title', 'Reistitel', $values),
                        $this->token('trip.startDate', 'Startdatum', $values),
                        $this->token('trip.endDate', 'Einddatum', $values),
                        $this->token('trip.totalDays', 'Aantal dagen', $values),
                        $this->token('days.count', 'Aantal dagsecties', $values),
                    ],
                ],
                [
                    'label' => 'Vandaag',
                    'tokens' => [
                        $this->token('currentDay.number', 'Dagnummer', $values),
                        $this->token('currentDay.title', 'Dagtitel', $values),
                        $this->token('currentDay.date', 'Datum', $values),
                        $this->token('nextDay.number', 'Volgend dagnummer', $values),
                        $this->token('nextDay.title', 'Volgende dagtitel', $values),
                    ],
                ],
                [
                    'label' => 'Volgende activiteit',
                    'tokens' => [
                        $this->token('nextActivity.title', 'Titel', $values),
                        $this->token('nextActivity.time', 'Tijd', $values),
                        $this->token('nextActivity.location', 'Locatie', $values),
                    ],
                ],
            ],
        ];
    }

    /**
     * @return list<array{number: int, title: string, date: ?\DateTimeImmutable, blocks: list<DayBlock>}>
     */
    private function days(TravelPlanContent $content, ?\DateTimeImmutable $startDate): array
    {
        $days = [];

        foreach ($content->destinations as $destination) {
            foreach ($destination->sections as $section) {
                if (SectionType::Day !== $section->type) {
                    continue;
                }

                $dayNumber = \max(1, (int) ($section->dayNumber ?: (\count($days) + 1)));
                $date = $startDate instanceof \DateTimeImmutable
                    ? $startDate->modify(\sprintf('+%d days', $dayNumber - 1))
                    : null;

                $days[] = [
                    'number' => $dayNumber,
                    'title' => $section->title ?: \sprintf('Dag %d', $dayNumber),
                    'date' => $date,
                    'blocks' => $section->blocks,
                ];
            }
        }

        \usort($days, static fn (array $left, array $right): int => $left['number'] <=> $right['number']);

        return $days;
    }

    /**
     * @param list<array{number: int, title: string, date: ?\DateTimeImmutable, blocks: list<DayBlock>}> $days
     * @param array{number?: int, title?: string, date?: \DateTimeImmutable} $currentDay
     *
     * @return array<string, mixed>
     */
    private function currentDay(array $days, array $currentDay, ?\DateTimeImmutable $startDate, ?\DateTimeImmutable $endDate): array
    {
        if ([] !== $currentDay) {
            $number = \max(1, (int) ($currentDay['number'] ?? 1));

            foreach ($days as $day) {
                if ($number === $day['number']) {
                return \array_replace($day, $currentDay);
                }
            }

            return [
                'number' => $number,
                'title' => (string) ($currentDay['title'] ?? \sprintf('Dag %d', $number)),
                'date' => $currentDay['date'] ?? null,
                'blocks' => [],
            ];
        }

        if ([] === $days) {
            return [];
        }

        $today = new \DateTimeImmutable('today', new \DateTimeZone('Europe/Amsterdam'));

        if ($startDate instanceof \DateTimeImmutable && $endDate instanceof \DateTimeImmutable && $today >= $startDate && $today <= $endDate) {
            $dayNumber = CompanionContentHelper::daysBetween($startDate, $today) + 1;

            foreach ($days as $day) {
                if ($dayNumber === $day['number']) {
                    return $day;
                }
            }
        }

        return $days[0];
    }

    /**
     * @param list<array{number: int, title: string, date: ?\DateTimeImmutable, blocks: list<DayBlock>}> $days
     * @param array<string, mixed> $currentDay
     *
     * @return array<string, mixed>
     */
    private function nextDay(array $days, array $currentDay): array
    {
        $currentNumber = $this->intFromArray($currentDay, 'number');

        foreach ($days as $day) {
            if ($day['number'] > $currentNumber) {
                return $day;
            }
        }

        return [];
    }

    /**
     * @param list<array{number: int, title: string, date: ?\DateTimeImmutable, blocks: list<DayBlock>}> $days
     * @param array<string, mixed> $currentDay
     *
     * @return array<string, string>
     */
    private function nextActivity(array $days, array $currentDay): array
    {
        $currentNumber = $this->intFromArray($currentDay, 'number');

        foreach ($days as $day) {
            if ($day['number'] < $currentNumber) {
                continue;
            }

            foreach ($day['blocks'] as $block) {
                if (BlockType::Activity !== $block->type) {
                    continue;
                }

                $title = $block->title;

                if ('' === $title) {
                    continue;
                }

                return [
                    'title' => $title,
                    'time' => $this->activityTime($block),
                    'location' => $block->location,
                ];
            }
        }

        return [];
    }

    private function activityTime(DayBlock $block): string
    {
        foreach ([$block->timeLabel, $block->startTime, $block->time] as $value) {
            if ('' !== \trim($value)) {
                return $value;
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $data
     */
    private function intFromArray(array $data, string $key): int
    {
        $value = $data[$key] ?? 0;

        return \is_scalar($value) ? (int) $value : 0;
    }

    private function formatDate(mixed $date): string
    {
        return $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : '';
    }

    private function totalDays(?\DateTimeImmutable $startDate, ?\DateTimeImmutable $endDate): string
    {
        if (!$startDate instanceof \DateTimeImmutable || !$endDate instanceof \DateTimeImmutable || $endDate < $startDate) {
            return '';
        }

        return (string) CompanionContentHelper::inclusiveDays($startDate, $endDate);
    }

    private function customerFirstName(object $customer): string
    {
        if (!\method_exists($customer, 'getFirstName')) {
            return '';
        }

        $value = $customer->getFirstName();

        return \is_scalar($value) ? (string) $value : '';
    }

    private function customerFullName(object $customer): string
    {
        if (\method_exists($customer, 'getFullName')) {
            $value = $customer->getFullName();
            $fullName = \is_scalar($value) ? \trim((string) $value) : '';

            if ('' !== $fullName) {
                return $fullName;
            }
        }

        $parts = [];

        foreach (['getFirstName', 'getLastName'] as $method) {
            if (!\method_exists($customer, $method)) {
                continue;
            }

            $value = $customer->{$method}();
            if (\is_scalar($value) && '' !== \trim((string) $value)) {
                $parts[] = \trim((string) $value);
            }
        }

        return \implode(' ', $parts);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function stringFromArray(array $data, string $key): string
    {
        $value = $data[$key] ?? '';

        if ($value instanceof \DateTimeInterface) {
            return $this->formatDate($value);
        }

        return \is_scalar($value) ? (string) $value : '';
    }

    /**
     * @param array<string, string> $values
     *
     * @return array{key: string, label: string, exampleValue: string, available: bool}
     */
    private function token(string $key, string $label, array $values): array
    {
        $value = $values[$key] ?? '';

        return [
            'key' => $key,
            'label' => $label,
            'exampleValue' => $value,
            'available' => '' !== \trim($value),
        ];
    }
}
