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
