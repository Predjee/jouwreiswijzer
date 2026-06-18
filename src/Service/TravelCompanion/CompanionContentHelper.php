<?php

declare(strict_types=1);

namespace App\Service\TravelCompanion;

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

    public static function createDate(mixed $value): ?\DateTimeImmutable
    {
        if ($value instanceof \DateTimeInterface) {
            return new \DateTimeImmutable($value->format('Y-m-d').' 00:00:00');
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
        $days = $startDate->diff($endDate)->days;

        return false === $days ? 0 : $days;
    }
}
