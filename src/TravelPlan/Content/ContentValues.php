<?php

declare(strict_types=1);

namespace App\TravelPlan\Content;

/**
 * Interne parse-helpers voor het contentmodel.
 *
 * @internal
 */
final class ContentValues
{
    private function __construct()
    {
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function string(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        return \is_scalar($value) ? \trim((string) $value) : '';
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function bool(array $data, string $key): bool
    {
        $value = $data[$key] ?? null;

        if (\is_bool($value)) {
            return $value;
        }

        if (\is_int($value)) {
            return 1 === $value;
        }

        if (\is_string($value)) {
            return \in_array(\strtolower(\trim($value)), ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }

    /**
     * Als string(), maar zonder trim: voor opslagvelden die whitespace
     * uit het CMS ongemoeid moeten laten (byte-identieke round-trips).
     *
     * @param array<array-key, mixed> $data
     */
    public static function rawString(array $data, string $key): string
    {
        $value = $data[$key] ?? '';

        return \is_scalar($value) ? (string) $value : '';
    }

    /**
     * @return array<string, mixed>
     */
    public static function stringKeyed(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        $result = [];

        foreach ($value as $key => $item) {
            if (\is_string($key)) {
                $result[$key] = $item;
            }
        }

        return $result;
    }

    /**
     * Media-referenties: array (Sulu-selectie) of scalar id; anders null.
     */
    public static function media(mixed $value): mixed
    {
        if (\is_array($value)) {
            return $value;
        }

        if (\is_scalar($value) && '' !== \trim((string) $value)) {
            return $value;
        }

        return null;
    }

    /**
     * Normaliseert een tijdwaarde naar "HH:MM" (of '' bij onbruikbare input).
     */
    public static function time(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i');
        }

        if (!\is_scalar($value)) {
            return '';
        }

        $value = \trim((string) $value);

        if ('' === $value) {
            return '';
        }

        if (1 === \preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/D', $value, $matches)) {
            return \sprintf('%02d:%s', (int) $matches[1], $matches[2]);
        }

        try {
            return (new \DateTimeImmutable($value))->format('H:i');
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * Normaliseert een datumwaarde naar "Y-m-d" (of '' bij onbruikbare input).
     */
    public static function date(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (!\is_scalar($value)) {
            return '';
        }

        $value = \trim((string) $value);

        if ('' === $value) {
            return '';
        }

        if (1 === \preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) {
            return $value;
        }

        try {
            return (new \DateTimeImmutable($value))->format('Y-m-d');
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * Inhoudsopgave-instelling: 'none' | 'one' | 'two' (incl. CMS-labels).
     */
    public static function tableOfContents(mixed $value): string
    {
        if (!\is_scalar($value)) {
            return 'none';
        }

        return match (\trim((string) $value)) {
            'one', 'Een laag' => 'one',
            'two', 'Twee lagen' => 'two',
            default => 'none',
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function arrayItems(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        $items = [];

        foreach ($value as $item) {
            if (\is_array($item)) {
                /** @var array<string, mixed> $item */
                $items[] = $item;
            }
        }

        return $items;
    }
}
