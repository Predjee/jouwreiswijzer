<?php

declare(strict_types=1);

namespace App\TravelPlan\View;

/**
 * @phpstan-type Variant array{background: string, edge: string, bar: string, accent: string|null, title: string, body: string, meta: string, link: string}
 */
final readonly class ThemeView
{
    /**
     * @param array<string, Variant> $variants
     */
    public function __construct(
        public string $navy,
        public string $gold,
        public string $goldLight,
        public string $textBody,
        public string $textSoft,
        public string $textContentLight,
        public string $textLight,
        public string $white,
        public string $cream,
        public string $zone,
        public string $edge,
        public string $edgeCard,
        public string $sectionRadius,
        public string $cardRadius,
        public array $variants,
    ) {
    }
}
