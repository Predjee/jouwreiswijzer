<?php

declare(strict_types=1);

namespace App\TravelPlan\Content;

/**
 * Pure migraties voor opgeslagen TravelPlanContent-arrays.
 *
 * Voeg een toekomstige migratiestap toe door TravelPlanContent::VERSION te
 * verhogen, in apply() een nieuwe fallthrough-case vanaf de oude versie toe te
 * voegen, en een test met een oude fixture vast te leggen. Elke stap moet een
 * nieuwe array teruggeven zonder database, services of andere side-effects.
 */
final class ContentMigrations
{
    /**
     * @param array<string, mixed> $content
     *
     * @return array<string, mixed>
     */
    public static function apply(array $content): array
    {
        $version = \is_int($content['_version'] ?? null) ? $content['_version'] : 1;

        return match ($version) {
            1 => $content,
            default => $content,
        };
    }
}
