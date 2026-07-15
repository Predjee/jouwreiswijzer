<?php

declare(strict_types=1);

namespace App\Companion;

/**
 * Gedeelde hulpmethoden voor TravelCompanionBuilder en TodayContextBuilder.
 */
final class CompanionContentHelper
{
    private const MONTHS = [
        1 => 'januari',
        2 => 'februari',
        3 => 'maart',
        4 => 'april',
        5 => 'mei',
        6 => 'juni',
        7 => 'juli',
        8 => 'augustus',
        9 => 'september',
        10 => 'oktober',
        11 => 'november',
        12 => 'december',
    ];

    /**
     * @param array<string, mixed> $data
     */
    public static function stringValue(array $data, string $key): string
    {
        $value = $data[$key] ?? '';

        return \is_scalar($value) ? (string) $value : '';
    }

    /**
     * @param array<string, mixed> $content
     *
     * @return list<array{destinationIndex: int, destination: array<string, mixed>}>
     */
    public static function destinations(array $content): array
    {
        if (!\is_array($content['destinations'] ?? null)) {
            return [];
        }

        $destinations = [];

        foreach ($content['destinations'] as $destinationIndex => $destination) {
            if (!\is_array($destination) || 'destination' !== ($destination['type'] ?? null)) {
                continue;
            }

            /** @var array<string, mixed> $destination CMS-content heeft stringkeys. */
            $destinations[] = [
                'destinationIndex' => (int) $destinationIndex,
                'destination' => $destination,
            ];
        }

        return $destinations;
    }

    /**
     * @param array<string, mixed> $content
     *
     * @return list<array{
     *     destinationIndex: int,
     *     sectionIndex: int,
     *     destination: array<string, mixed>,
     *     section: array<string, mixed>
     * }>
     */
    public static function destinationSections(array $content): array
    {
        $sections = [];

        foreach (self::destinations($content) as $destinationData) {
            $destination = $destinationData['destination'];

            if (!\is_array($destination['sections'] ?? null)) {
                continue;
            }

            foreach ($destination['sections'] as $sectionIndex => $section) {
                if (!\is_array($section)) {
                    continue;
                }

                /** @var array<string, mixed> $section CMS-content heeft stringkeys. */
                $sections[] = [
                    'destinationIndex' => $destinationData['destinationIndex'],
                    'sectionIndex' => (int) $sectionIndex,
                    'destination' => $destination,
                    'section' => $section,
                ];
            }
        }

        return $sections;
    }

    public static function createDate(mixed $value): ?\DateTimeImmutable
    {
        if ($value instanceof \DateTimeInterface) {
            return new \DateTimeImmutable($value->format('Y-m-d') . ' 00:00:00');
        }

        if (!\is_scalar($value)) {
            return null;
        }

        $value = \trim((string) $value);

        if (1 !== \preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = \DateTimeImmutable::getLastErrors();

        if (
            !$date instanceof \DateTimeImmutable
            || (false !== $errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
        ) {
            return null;
        }

        return $date;
    }

    /**
     * @param array<string, mixed> $content
     */
    public static function hasTripStarted(array $content): bool
    {
        $tripProfile = \is_array($content['tripProfile'] ?? null) ? $content['tripProfile'] : [];
        $startDate = self::createDate($tripProfile['startDate'] ?? null);

        return $startDate instanceof \DateTimeImmutable
            && $startDate <= new \DateTimeImmutable('today');
    }

    public static function dateLabel(\DateTimeImmutable $date): string
    {
        return \sprintf('%d %s', (int) $date->format('j'), self::MONTHS[(int) $date->format('n')]);
    }

    public static function inclusiveDays(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate): int
    {
        return self::daysBetween($startDate, $endDate) + 1;
    }

    public static function daysBetween(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate): int
    {
        // DateInterval::$days is false bij een niet-berekenbaar verschil;
        // de int-cast maakt daar 0 van zonder dode-vergelijking-melding.
        return (int) $startDate->diff($endDate)->days;
    }

    /**
     * @param array<string, mixed> $block
     */
    public static function startTime(array $block): string
    {
        return self::normalizeTime(self::stringValue($block, 'startTime'))
            ?: self::normalizeTime(self::stringValue($block, 'time'));
    }

    /**
     * @param array<string, mixed> $block
     */
    public static function endTime(array $block): string
    {
        return self::normalizeTime(self::stringValue($block, 'endTime'));
    }

    /**
     * @param array<string, mixed> $block
     */
    public static function timeRangeLabel(array $block): string
    {
        $startTime = self::startTime($block);
        $endTime = self::endTime($block);

        if ('' === $startTime) {
            return '';
        }

        if ('' === $endTime || $endTime === $startTime) {
            return $startTime;
        }

        return \sprintf('%s - %s', $startTime, $endTime);
    }

    public static function normalizeTime(string $time): string
    {
        $time = \trim($time);

        if (1 !== \preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/D', $time, $matches)) {
            return '';
        }

        return \sprintf('%02d:%s', (int) $matches[1], $matches[2]);
    }
}
